<?php

declare(strict_types=1);

namespace app\components\aliexpress;

use app\models\Category;
use app\models\Product;
use app\models\ProductAttribute;
use app\models\ProductImage;
use app\models\ProductVariant;
use app\models\Store;
use RuntimeException;
use Throwable;
use Yii;

/**
 * Builds/refreshes a full Product aggregate for (Store, externalId): Affiliate API for the
 * reliable core (price, currency, affiliate link, category) plus the product scraper for
 * gallery / variants / attributes. Upserts product + children in a transaction. Idempotent.
 */
final class ProductImporter
{
    public function __construct(
        private readonly AliExpressApiClient     $apiClient = new AliExpressApiClient(),
        private readonly AliExpressProductScraper $productScraper = new AliExpressProductScraper(),
    ) {
    }

    public function import(Store $store, string $externalId): Product
    {
        $core = $this->apiClient->fetchProductByItemId($externalId);

        $detail = ['description' => null, 'images' => [], 'variants' => [], 'attributes' => []];
        try {
            $detail = $this->productScraper->fetch($externalId);
        } catch (Throwable $e) {
            Yii::warning("Product scrape failed for {$externalId}: {$e->getMessage()}", __METHOD__);
        }

        $transaction = Yii::$app->db->beginTransaction();
        try {
            $product = Product::findOrNew($store->id, $externalId);
            $product->category_id   = Category::resolveLeafFromApi($core) ?? $product->category_id;
            $product->title         = $core['name'] ?? $product->title;
            $product->product_url   = $core['url'] ?? $product->product_url;
            $product->affiliate_url = $core['url'] ?? $product->affiliate_url;
            $product->main_image    = $core['image'] ?? $product->main_image;
            $product->currency_code = strtoupper((string)($core['currency_code'] ?? 'USD')) ?: 'USD';
            $product->price         = isset($core['price_cents']) && is_numeric($core['price_cents']) ? (int)$core['price_cents'] : $product->price;
            $product->availability  = $core['availability'] ?? $product->availability;
            $product->rating_value     = isset($core['rating_value']) ? (string)$core['rating_value'] : $product->rating_value;
            $product->rating_scale_max = isset($core['rating_scale_max']) ? (string)$core['rating_scale_max'] : $product->rating_scale_max;
            $product->review_count  = isset($core['review_count']) ? (int)$core['review_count'] : $product->review_count;
            if ($detail['description'] !== null) {
                $product->description = $detail['description'];
            }
            $product->source = 'aliexpress';
            $product->last_detail_synced_at = time();
            $product->last_price_synced_at  = time();
            if (!$product->save()) {
                throw new RuntimeException('Failed to save product: ' . implode('; ', $product->getFirstErrors()));
            }

            $this->syncImages($product, $detail['images'], (string)$product->main_image);
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

    /** Lightweight refresh: only re-pull price/currency/availability/rating from the official API. */
    public function refreshPrice(Product $product): void
    {
        $core = $this->apiClient->fetchProductByItemId($product->external_id);
        $product->currency_code = strtoupper((string)($core['currency_code'] ?? $product->currency_code)) ?: $product->currency_code;
        $product->price = isset($core['price_cents']) && is_numeric($core['price_cents']) ? (int)$core['price_cents'] : $product->price;
        $product->availability = $core['availability'] ?? $product->availability;
        $product->rating_value = isset($core['rating_value']) ? (string)$core['rating_value'] : $product->rating_value;
        $product->review_count = isset($core['review_count']) ? (int)$core['review_count'] : $product->review_count;
        $product->last_price_synced_at = time();
        $product->save(false);
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
