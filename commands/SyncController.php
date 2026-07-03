<?php

declare(strict_types=1);

namespace app\commands;

use app\components\aliexpress\SyncJobDispatcher;
use app\models\SyncJob;
use Throwable;
use Yii;
use yii\console\Controller;
use yii\console\ExitCode;

/**
 * Generic queue worker: claims pending sync_job rows and dispatches them by type.
 * Run frequently from cron, e.g. `* /10 * * * * php yii sync/process`.
 */
final class SyncController extends Controller
{
    public function actionProcess(?int $limit = null): int
    {
        $limit = $limit ?? (int)(Yii::$app->params['sync.batchSize'] ?? 20);
        $maxAttempts = (int)(Yii::$app->params['sync.maxAttempts'] ?? 5);
        $dispatcher = new SyncJobDispatcher();
        $processed = 0;

        for ($i = 0; $i < $limit; $i++) {
            $job = SyncJob::claimNext();
            if ($job === null) {
                break;
            }

            try {
                $dispatcher->dispatch($job);
                $job->markDone();
                $processed++;
                $this->stdout("OK   job #{$job->id} ({$job->type})\n");
            } catch (Throwable $e) {
                $job->markFailed($e->getMessage(), $maxAttempts);
                $this->stderr("FAIL job #{$job->id} ({$job->type}): {$e->getMessage()}\n");
                Yii::error("SyncJob #{$job->id} failed: {$e->getMessage()}", __METHOD__);
            }

            sleep(random_int(3, 6)); // rate limit between external calls
        }

        $this->stdout("Processed {$processed} job(s).\n");

        return ExitCode::OK;
    }
}
