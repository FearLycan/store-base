<?php

declare(strict_types=1);

namespace app\components\aliexpress;

use app\components\llm\TitleRewriter;
use app\enums\ProductStatusEnum;
use app\enums\SyncJobTypeEnum;
use app\models\Product;
use app\models\ProductReview;
use app\models\Store;
use app\models\SyncJob;
use RuntimeException;
use Throwable;
use Yii;

/**
 * Executes one SyncJob by type and enqueues follow-up jobs. The console worker
 * (SyncController) handles claiming, retry/backoff and rate limiting.
 */
final class SyncJobDispatcher
{
    public function __construct(
        private readonly AliExpressStoreScraper  $storeScraper = new AliExpressStoreScraper(),
        private readonly ProductImporter         $importer = new ProductImporter(),
        private readonly AliExpressReviewScraper  $reviewScraper = new AliExpressReviewScraper(),
        private readonly TitleRewriter            $titleRewriter = new TitleRewriter(),
    ) {
    }

    public function dispatch(SyncJob $job): void
    {
        $type = SyncJobTypeEnum::from($job->type);
        match ($type) {
            SyncJobTypeEnum::STORE_DISCOVERY => $this->discoverStore($job),
            SyncJobTypeEnum::PRODUCT_DETAIL  => $this->importProduct($job),
            SyncJobTypeEnum::PRODUCT_REVIEWS => $this->syncReviews($job),
            SyncJobTypeEnum::PRICE_REFRESH   => $this->refreshPrice($job),
            SyncJobTypeEnum::TITLE_REWRITE   => $this->rewriteTitle($job),
        };
    }

    private function discoverStore(SyncJob $job): void
    {
        $store = Store::findOne((int)$job->store_id) ?? throw new RuntimeException('Store not found.');
        $stubs = $this->storeScraper->fetchProductStubs($store);

        foreach ($stubs as $stub) {
            $externalId = $stub['external_id'];
            $exists = Product::find()->where(['store_id' => $store->id, 'external_id' => $externalId])->exists();
            $pending = SyncJob::find()
                ->where(['type' => SyncJobTypeEnum::PRODUCT_DETAIL->value, 'store_id' => $store->id])
                ->andWhere(['in', 'status', ['pending', 'processing']])
                ->andWhere(['like', 'payload_json', $externalId])
                ->exists();
            if ($exists || $pending) {
                continue;
            }
            SyncJob::enqueue(SyncJobTypeEnum::PRODUCT_DETAIL, $store->id, null, ['external_id' => $externalId]);
        }

        $store->last_discovery_at = time();
        $store->save(false, ['last_discovery_at']);
    }

    private function importProduct(SyncJob $job): void
    {
        $store = Store::findOne((int)$job->store_id) ?? throw new RuntimeException('Store not found.');
        $externalId = (string)($job->payload_json['external_id'] ?? '');
        if ($externalId === '') {
            throw new RuntimeException('Missing external_id in payload.');
        }
        // Manual admin imports carry trusted=true: we skip the seller guard and take the paste as-is.
        // Auto-discovery jobs omit it, so the shop_id guard drops cross-sold foreign products.
        $verifySeller = empty($job->payload_json['trusted']);
        try {
            $product = $this->importer->import($store, $externalId, $verifySeller);
        } catch (ForeignSellerException $e) {
            // Not this store's seller (Choice cross-sell) — skip quietly: no product, no follow-ups.
            Yii::info($e->getMessage(), __METHOD__);
            return;
        }
        SyncJob::enqueue(SyncJobTypeEnum::PRODUCT_REVIEWS, $store->id, $product->id, ['external_id' => $externalId]);

        // Publish inline: humanise the title and flip draft -> active in this same job, instead of
        // enqueuing a separate TITLE_REWRITE. A dedicated publish job would sort *after* the whole
        // import backlog (higher id) and starve — inline makes a new product go live in one job.
        if ($product->display_title === null || $product->display_title === '') {
            $this->applyTitleAndPublish($product);
        }
    }

    /**
     * Legacy TITLE_REWRITE handler, kept so jobs enqueued before publishing moved inline still drain.
     * New imports publish inline via {@see importProduct}; this only reaches the queue's backlog.
     */
    private function rewriteTitle(SyncJob $job): void
    {
        $product = Product::findOne((int)$job->product_id) ?? throw new RuntimeException('Product not found.');
        $this->applyTitleAndPublish($product);
    }

    /**
     * Rewrites the raw marketplace title into a human-friendly display_title, derives the slug from
     * it, and publishes the product (draft -> active). Falls back to a cheap offline cleanup when the
     * LLM is unavailable so the product still goes live. Idempotent — safe to re-run on a live product.
     */
    private function applyTitleAndPublish(Product $product): void
    {
        $raw = (string)$product->title;

        try {
            $clean = $this->titleRewriter->rewrite($raw);
        } catch (Throwable $e) {
            Yii::warning("Title rewrite fell back for product #{$product->id}: {$e->getMessage()}", __METHOD__);
            $clean = TitleRewriter::fallback($raw);
        }

        $product->display_title = $clean;
        $product->slug = Product::generateUniqueSlug($clean, $product->id);
        if ($product->status === ProductStatusEnum::DRAFT->value) {
            $product->status = ProductStatusEnum::ACTIVE->value;
        }
        $product->save(false, ['display_title', 'slug', 'status']);
    }

    private function syncReviews(SyncJob $job): void
    {
        $product = Product::findOne((int)$job->product_id) ?? throw new RuntimeException('Product not found.');
        // Real full-corpus aggregates (total, star distribution, photo count + strip) so
        // the reviews UI is self-consistent — "all" >= any filter, and the photo strip
        // reflects what the "with photos" filter returns. fetchAggregates uses '0' for
        // sellerAdminSeq internally, so the store's seller_admin_seq is no longer needed.
        $agg = $this->reviewScraper->fetchAggregates($product->external_id);
        ProductReview::syncByProduct($product, $agg['reviews']);
        $product->review_impressions  = $agg['impressions'] !== [] ? $agg['impressions'] : null; // includes 'id'
        $product->review_total        = $agg['total'] ?: null;
        $product->review_rating_dist  = array_sum($agg['dist']) > 0 ? $agg['dist'] : null;
        $product->review_image_count  = $agg['imageCount'];
        $product->review_photos       = $agg['photos'] !== [] ? $agg['photos'] : null;
        $product->save(false, ['review_impressions', 'review_total', 'review_rating_dist', 'review_image_count', 'review_photos']);
    }

    private function refreshPrice(SyncJob $job): void
    {
        $product = Product::findOne((int)$job->product_id) ?? throw new RuntimeException('Product not found.');
        $this->importer->refreshPrice($product);
    }
}
