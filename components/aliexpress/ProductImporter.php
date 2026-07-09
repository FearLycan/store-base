<?php

declare(strict_types=1);

namespace app\components\aliexpress;

use app\enums\ProductStatusEnum;
use app\models\Category;
use app\models\Product;
use app\models\ProductAttribute;
use app\models\ProductImage;
use app\models\ProductPriceHistory;
use app\models\ProductVariant;
use app\models\Store;
use RuntimeException;
use Throwable;
use Yii;

/**
 * Builds/refreshes a full Product aggregate for (Store, externalId): Affiliate API for the
 * reliable core (price, currency, affiliate link, category) plus the Dropshipping API for the
 * HD gallery / variants / attributes — with the x5sec product scraper as fallback. Upserts
 * product + children in a transaction. Idempotent.
 */
final class ProductImporter
{
    public function __construct(
        private readonly AliExpressApiClient     $apiClient = new AliExpressApiClient(),
        private readonly AliExpressProductScraper $productScraper = new AliExpressProductScraper(),
        private readonly AliExpressDsClient       $dsClient = new AliExpressDsClient(),
    ) {
    }

    /**
     * @param bool $verifySeller reject the product when its Affiliate shop_id doesn't match the store
     *                           (auto-discovery). Manual admin imports pass false — we trust the paste.
     */
    public function import(Store $store, string $externalId, bool $verifySeller = true): Product
    {
        // Affiliate API is the primary core (price + affiliate link). When it has no record of the
        // product (non-commissionable / geo-restricted), we still import it from the DS API below —
        // just without an affiliate link. Better an extra catalog entry than none.
        $core = $this->tryAffiliateCore($externalId);
        if ($core !== null && $verifySeller) {
            $this->assertBelongsToStore($store, $externalId, $core);
        }

        $detail = $this->fetchDetail($externalId);

        if ($core === null) {
            // No affiliate core — build it from the DS detail we just fetched (throws to skip if DS
            // has nothing either, i.e. the product genuinely doesn't exist anywhere).
            $core = $this->coreFromDs($externalId, $detail);
        }

        $transaction = Yii::$app->db->beginTransaction();
        try {
            $product = Product::findOrNew($store->id, $externalId);
            // New products stay hidden (draft) until the TITLE_REWRITE job humanises the title and
            // assigns a slug; existing products keep whatever status they already have.
            if ($product->isNewRecord) {
                $product->status = ProductStatusEnum::DRAFT->value;
            }
            // Affiliate API only reaches L2 ("Fashion Jewelry"); the DS "Item Type" attribute carries
            // the real product type ("Earrings", "Rings"), so hang it as a level-3 child. Fall back to
            // the affiliate leaf when the attribute is absent (some niches, e.g. phone cases, omit it).
            $affiliateLeafId = Category::resolveLeafFromApi($core);
            $itemType = $this->extractItemType($detail['attributes']);
            $product->category_id = Category::resolveItemTypeChild($affiliateLeafId, $itemType)
                ?? $affiliateLeafId
                ?? $product->category_id;
            $product->title         = $core['name'] ?? $product->title;
            $product->product_url   = $core['url'] ?? $product->product_url;
            // DS-only products carry an explicit null affiliate_url (no commissionable link); the
            // `?? existing` keeps any link a product already had from an earlier affiliable import.
            $product->affiliate_url = $core['affiliate_url'] ?? $product->affiliate_url;
            $product->main_image    = $core['image'] ?? $product->main_image;
            $product->video_url     = $core['video_url'] ?? $product->video_url;
            $product->currency_code = strtoupper((string)($core['currency_code'] ?? 'USD')) ?: 'USD';
            $oldPrice = $product->price;
            $product->price         = isset($core['price_cents']) && is_numeric($core['price_cents']) ? (int)$core['price_cents'] : $product->price;
            $product->original_price = isset($core['original_price_cents']) && is_numeric($core['original_price_cents']) ? (int)$core['original_price_cents'] : $product->original_price;
            $priceChanged = $product->price !== null && $product->price !== $oldPrice;
            if ($priceChanged && $oldPrice !== null) {
                $product->previous_price = $oldPrice;
                $product->price_changed_at = time();
            }
            $product->availability  = $core['availability'] ?? $product->availability;
            $product->rating_value     = isset($core['rating_value']) ? (string)$core['rating_value'] : $product->rating_value;
            $product->rating_scale_max = isset($core['rating_scale_max']) ? (string)$core['rating_scale_max'] : $product->rating_scale_max;
            $product->orders_count  = isset($core['orders_count']) ? (int)$core['orders_count'] : $product->orders_count;
            if ($detail['description'] !== null) {
                $product->description = $detail['description'];
            }
            $product->source = 'aliexpress';
            $product->last_detail_synced_at = time();
            $product->last_price_synced_at  = time();
            if (!$product->save()) {
                throw new RuntimeException('Failed to save product: ' . implode('; ', $product->getFirstErrors()));
            }

            if ($priceChanged) {
                ProductPriceHistory::add($product->id, $product->price, $product->original_price, $product->currency_code, time());
            }

            // Scraper images are HD and SKU-aware; the API gallery is the fallback when the
            // risk-controlled PDP call fails (expired x5sec).
            $galleryImages = $detail['images'] !== [] ? $detail['images'] : (array)($core['images'] ?? []);
            $this->syncImages($product, $galleryImages, (string)$product->main_image);
            $this->syncVariants($product, $detail['variants'], $product->currency_code);
            $this->syncAttributes($product, $detail['attributes']);

            $transaction->commit();
        } catch (Throwable $e) {
            $transaction->rollBack();
            throw $e;
        }

        $store->recountProducts();

        return $product;
    }

    /**
     * The Affiliate API core, or null when the product is not in the affiliate program (the API
     * returns zero records). Null is the caller's cue to fall back to a DS-only import.
     *
     * @return array<string,mixed>|null
     */
    private function tryAffiliateCore(string $externalId): ?array
    {
        try {
            return $this->apiClient->fetchProductByItemId($externalId);
        } catch (ProductNotAffiliableException $e) {
            Yii::info("Affiliate API has no record for {$externalId}; importing from DS only (no affiliate link).", __METHOD__);

            return null;
        }
    }

    /**
     * Build an affiliate-shaped core from the DS detail bundle, for products the Affiliate API doesn't
     * carry. `url` is the plain product page (GoController redirects there when affiliate_url is null),
     * and `affiliate_url` is explicitly null — no commission, but still a usable catalog entry.
     * Throws {@see ProductNotAffiliableException} when DS returned nothing either, so the queue skips it.
     *
     * @param array{base?:array<string,mixed>} $detail
     * @return array<string,mixed>
     */
    private function coreFromDs(string $externalId, array $detail): array
    {
        $base = $detail['base'] ?? null;
        if (!is_array($base) || ($base['title'] ?? null) === null) {
            throw new ProductNotAffiliableException(
                "AliExpress product {$externalId} is not in the affiliate program and the Dropshipping "
                . 'API returned no usable data — nothing to import. Skipped.'
            );
        }

        $ratingValue = $base['rating_value'] ?? null;

        return [
            'external_id'          => $externalId,
            'name'                 => $base['title'],
            'url'                  => 'https://www.aliexpress.com/item/' . $externalId . '.html',
            'affiliate_url'        => null,
            'image'                => $base['main_image'] ?? null,
            'images'               => $detail['images'] ?? [],
            'video_url'            => null,
            'currency_code'        => $base['currency_code'] ?? 'USD',
            'price_cents'          => $base['price_cents'] ?? null,
            'original_price_cents' => $base['original_price_cents'] ?? null,
            'availability'         => null,
            'rating_value'         => $ratingValue,
            'rating_scale_max'     => $ratingValue !== null ? 5.0 : null,
            'orders_count'         => $base['orders_count'] ?? 0,
            'shop_id'              => null,
            'category_l1_id'       => null,
            'category_l1_name'     => null,
            'category_l2_id'       => null,
            'category_l2_name'     => null,
        ];
    }

    /**
     * Guard against the store listing smuggling in another seller's product (AliExpress "Choice"
     * cross-sell): the Affiliate `shop_id` is the authoritative seller, and for genuine store items
     * it equals the store's `external_store_id` (the /store/<id> number). A mismatch means this item
     * is not sold by the store we queued it under, so we refuse to attribute it here.
     *
     * @param array<string,mixed> $core
     */
    private function assertBelongsToStore(Store $store, string $externalId, array $core): void
    {
        $expected = trim((string)$store->external_store_id);
        $actual   = trim((string)($core['shop_id'] ?? ''));
        if ($expected !== '' && $actual !== '' && $actual !== $expected) {
            throw new ForeignSellerException(
                "Product {$externalId} belongs to shop {$actual}, not store #{$store->id} (shop {$expected}) — skipped."
            );
        }
    }

    /**
     * Detail bundle (description / HD images / variants / attributes) from the Dropshipping API,
     * falling back to the x5sec scraper when DS is unavailable (not connected / token / API error).
     *
     * @return array{description:?string, images:array<int,string>, variants:array<int,array>, attributes:array<int,array{name:string,value:?string}>}
     */
    private function fetchDetail(string $externalId): array
    {
        try {
            return $this->dsClient->fetch($externalId);
        } catch (Throwable $e) {
            Yii::warning("DS detail failed for {$externalId}: {$e->getMessage()}", __METHOD__);
        }

        try {
            return $this->productScraper->fetch($externalId);
        } catch (Throwable $e) {
            Yii::warning("Product scrape fallback failed for {$externalId}: {$e->getMessage()}", __METHOD__);
        }

        return ['description' => null, 'images' => [], 'variants' => [], 'attributes' => []];
    }

    /** Lightweight refresh: only re-pull price/currency/availability/rating from the official API. */
    public function refreshPrice(Product $product): void
    {
        // Mirror import(): prefer the Affiliate core, fall back to DS for non-commissionable products
        // so their price/availability still refresh instead of the job perpetually skipping.
        $core = $this->tryAffiliateCore($product->external_id)
            ?? $this->coreFromDs($product->external_id, $this->fetchDetail($product->external_id));
        $product->currency_code = strtoupper((string)($core['currency_code'] ?? $product->currency_code)) ?: $product->currency_code;
        $oldPrice = $product->price;
        $product->price = isset($core['price_cents']) && is_numeric($core['price_cents']) ? (int)$core['price_cents'] : $product->price;
        $product->original_price = isset($core['original_price_cents']) && is_numeric($core['original_price_cents']) ? (int)$core['original_price_cents'] : $product->original_price;
        $product->availability = $core['availability'] ?? $product->availability;
        $product->rating_value = isset($core['rating_value']) ? (string)$core['rating_value'] : $product->rating_value;
        $product->orders_count = isset($core['orders_count']) ? (int)$core['orders_count'] : $product->orders_count;
        $product->last_price_synced_at = time();
        $priceChanged = $product->price !== null && $product->price !== $oldPrice;
        if ($priceChanged && $oldPrice !== null) {
            $product->previous_price = $oldPrice;
            $product->price_changed_at = time();
        }
        $product->save(false);
        if ($priceChanged) {
            ProductPriceHistory::add($product->id, $product->price, $product->original_price, $product->currency_code, time());
        }
    }

    /**
     * The DS "Item Type" property value (e.g. "Earrings"), or null when the product doesn't carry one.
     *
     * @param array<int,array{name:string,value:?string}> $attributes
     */
    private function extractItemType(array $attributes): ?string
    {
        foreach ($attributes as $a) {
            if (strtolower(trim((string)($a['name'] ?? ''))) === 'item type') {
                $value = trim((string)($a['value'] ?? ''));
                if ($value !== '') {
                    return $value;
                }
            }
        }

        return null;
    }

    private function syncImages(Product $product, array $images, string $mainImage): void
    {
        ProductImage::deleteAll(['product_id' => $product->id]);
        $urls = $images !== [] ? $images : array_filter([$mainImage]);
        $position = 0;
        foreach ($urls as $url) {
            $row = new ProductImage();
            $row->product_id = $product->id;
            $row->url = (string)$url;
            $row->position = $position;
            $row->is_main = $position === 0 ? 1 : 0;
            $row->save();
            $position++;
        }
    }

    private function syncVariants(Product $product, array $variants, string $currency): void
    {
        // Guard against a degraded detail fetch wiping good data: when the Dropshipping token has
        // lapsed the x5sec scraper fallback is captcha-blocked, so {@see fetchDetail()} returns an
        // empty bundle. A healthy DS response always carries at least the product's SKU rows, so an
        // empty list here means "fetch failed", not "product genuinely has no variants" — keep the
        // variants already stored instead of deleting them. A real re-sync (non-empty) still replaces
        // the whole set below, so once DS is reconnected the next import repopulates them correctly.
        if ($variants === []) {
            return;
        }

        ProductVariant::deleteAll(['product_id' => $product->id]);
        foreach ($variants as $v) {
            $row = new ProductVariant();
            $row->product_id      = $product->id;
            $row->external_sku_id = isset($v['external_sku_id']) ? (string)$v['external_sku_id'] : null;
            $row->name            = $v['name'] ?? null;
            $row->options_json    = $v['options'] ?? null;
            $row->price           = isset($v['price']) && is_numeric($v['price']) ? (int)$v['price'] : null;
            $row->original_price  = isset($v['original_price']) && is_numeric($v['original_price']) ? (int)$v['original_price'] : null;
            $row->stock           = isset($v['stock']) && is_numeric($v['stock']) ? (int)$v['stock'] : null;
            $row->image           = $v['image'] ?? null;
            $row->currency_code   = $currency;
            $row->save();
        }
    }

    private function syncAttributes(Product $product, array $attributes): void
    {
        // Same degraded-fetch guard as syncVariants(): don't wipe existing specs when the detail
        // fetch came back empty (DS down + scraper blocked). A real re-sync replaces them wholesale.
        if ($attributes === []) {
            return;
        }

        ProductAttribute::deleteAll(['product_id' => $product->id]);
        $position = 0;
        foreach ($attributes as $a) {
            if (!isset($a['name']) || trim((string)$a['name']) === '') {
                continue;
            }
            $row = new ProductAttribute();
            $row->product_id = $product->id;
            $row->name = (string)$a['name'];
            $row->value = isset($a['value']) ? (string)$a['value'] : null;
            $row->position = $position++;
            $row->save();
        }
    }
}
