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

    public function import(Store $store, string $externalId): Product
    {
        $core = $this->apiClient->fetchProductByItemId($externalId);

        $detail = $this->fetchDetail($externalId);

        $transaction = Yii::$app->db->beginTransaction();
        try {
            $product = Product::findOrNew($store->id, $externalId);
            // New products stay hidden (draft) until the TITLE_REWRITE job humanises the title and
            // assigns a slug; existing products keep whatever status they already have.
            if ($product->isNewRecord) {
                $product->status = ProductStatusEnum::DRAFT->value;
            }
            $product->category_id   = Category::resolveLeafFromApi($core) ?? $product->category_id;
            $product->title         = $core['name'] ?? $product->title;
            $product->product_url   = $core['url'] ?? $product->product_url;
            $product->affiliate_url = $core['url'] ?? $product->affiliate_url;
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
        $core = $this->apiClient->fetchProductByItemId($product->external_id);
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
