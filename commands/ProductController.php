<?php

declare(strict_types=1);

namespace app\commands;

use app\enums\SyncJobTypeEnum;
use app\models\Product;
use app\models\SyncJob;
use Yii;
use yii\console\Controller;
use yii\console\ExitCode;

/**
 * Queues price-refresh jobs for products whose price data has gone stale.
 * Run e.g. daily: `php yii product/refresh-prices`.
 */
final class ProductController extends Controller
{
    /**
     * Queues a full detail re-import (DS API: HD gallery / variants / attributes) for products that
     * already exist. Unlike `store/discover`, which only ingests new products, this refreshes the
     * existing catalogue. Process the queued jobs afterwards with `php yii sync/process`.
     *
     * @param int|null $storeId only this store when given
     */
    public function actionReimport(?int $storeId = null, int $limit = 1000): int
    {
        $query = Product::find()->limit($limit);
        if ($storeId !== null) {
            $query->andWhere(['store_id' => $storeId]);
        }

        $queued = 0;
        foreach ($query->all() as $product) {
            SyncJob::enqueue(SyncJobTypeEnum::PRODUCT_DETAIL, $product->store_id, null, ['external_id' => $product->external_id]);
            $queued++;
        }

        $this->stdout("Queued {$queued} detail re-import job(s). Run `php yii sync/process` to execute.\n");

        return ExitCode::OK;
    }

    public function actionRefreshPrices(int $limit = 500): int
    {
        $intervalDays = (int)(Yii::$app->params['sync.priceRefreshIntervalDays'] ?? 1);
        $cutoff = time() - ($intervalDays * 86400);

        $products = Product::find()
            ->where(['or', ['last_price_synced_at' => null], ['<=', 'last_price_synced_at', $cutoff]])
            ->limit($limit)
            ->all();

        $queued = 0;
        foreach ($products as $product) {
            SyncJob::enqueue(SyncJobTypeEnum::PRICE_REFRESH, $product->store_id, $product->id, ['external_id' => $product->external_id]);
            $queued++;
        }

        $this->stdout("Queued {$queued} price-refresh job(s).\n");

        return ExitCode::OK;
    }
}
