<?php

declare(strict_types=1);

namespace app\components\aliexpress;

use app\enums\SyncJobTypeEnum;
use app\models\Product;
use app\models\ProductReview;
use app\models\Store;
use app\models\SyncJob;
use RuntimeException;

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
        $product = $this->importer->import($store, $externalId);
        SyncJob::enqueue(SyncJobTypeEnum::PRODUCT_REVIEWS, $store->id, $product->id, ['external_id' => $externalId]);
    }

    private function syncReviews(SyncJob $job): void
    {
        $product = Product::findOne((int)$job->product_id) ?? throw new RuntimeException('Product not found.');
        $sellerAdminSeq = (string)($product->store->seller_admin_seq ?? '');
        $result = $this->reviewScraper->fetch($product->external_id, $sellerAdminSeq);
        ProductReview::syncByProduct($product, $result['reviews']);
        $product->review_impressions = $result['impressions'] !== [] ? $result['impressions'] : null;
        $product->save(false, ['review_impressions']);
    }

    private function refreshPrice(SyncJob $job): void
    {
        $product = Product::findOne((int)$job->product_id) ?? throw new RuntimeException('Product not found.');
        $this->importer->refreshPrice($product);
    }
}
