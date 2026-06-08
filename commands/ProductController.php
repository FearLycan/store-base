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
