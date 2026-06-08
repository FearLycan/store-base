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
 * Queues review-sync jobs for products whose reviews have gone stale.
 * Run e.g. every 1-3 days: `php yii review/sync`.
 */
final class ReviewController extends Controller
{
    public function actionSync(int $limit = 500): int
    {
        $intervalDays = (int)(Yii::$app->params['sync.reviewRefreshIntervalDays'] ?? 3);
        $cutoff = time() - ($intervalDays * 86400);

        $products = Product::find()
            ->where(['or', ['last_review_synced_at' => null], ['<=', 'last_review_synced_at', $cutoff]])
            ->limit($limit)
            ->all();

        $queued = 0;
        foreach ($products as $product) {
            SyncJob::enqueue(SyncJobTypeEnum::PRODUCT_REVIEWS, $product->store_id, $product->id, ['external_id' => $product->external_id]);
            $queued++;
        }

        $this->stdout("Queued {$queued} review-sync job(s).\n");

        return ExitCode::OK;
    }
}
