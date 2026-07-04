<?php

declare(strict_types=1);

namespace app\commands;

use app\components\aliexpress\NonRetryableJobException;
use app\components\aliexpress\SyncJobDispatcher;
use app\enums\SyncJobTypeEnum;
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
    /**
     * @param int|null $limit Max jobs to process this run. Null (the cron default) drains the queue
     *                        until it is empty or the time budget runs out, so throughput is bounded
     *                        by real work — not by how often cron fires. The mutex keeps it single.
     */
    public function actionProcess(?int $limit = null): int
    {
        // Only one worker at a time: overlapping cron runs would claim jobs in parallel and
        // burst the AliExpress per-second limit. A second run just exits instead of doubling up.
        $lock = 'sync/process';
        if (!Yii::$app->mutex->acquire($lock)) {
            $this->stdout("sync/process already running; skipping.\n");

            return ExitCode::OK;
        }

        try {
            return $this->processQueue($limit);
        } finally {
            Yii::$app->mutex->release($lock);
        }
    }

    private function processQueue(?int $limit): int
    {
        $maxAttempts = (int)(Yii::$app->params['sync.maxAttempts'] ?? 5);
        // How long a single run may keep draining. The mutex means overlapping cron ticks skip while
        // this runs, so it can safely span many cron intervals; it exits early when the queue empties.
        $deadline = time() + (int)(Yii::$app->params['sync.timeBudgetSeconds'] ?? 3300);
        $dispatcher = new SyncJobDispatcher();
        $processed = 0;

        while (($limit === null || $processed < $limit) && time() < $deadline) {
            $job = SyncJob::claimNext();
            if ($job === null) {
                break; // queue drained
            }

            try {
                $dispatcher->dispatch($job);
                $job->markDone();
                $processed++;
                $this->stdout("OK   job #{$job->id} ({$job->type})\n");
            } catch (NonRetryableJobException $e) {
                // Permanent condition (e.g. product not in the affiliate program): park it terminally
                // so it stops occupying the queue, instead of retrying to the max attempt budget.
                $job->markSkipped($e->getMessage());
                $this->stdout("SKIP job #{$job->id} ({$job->type}): {$e->getMessage()}\n");
            } catch (Throwable $e) {
                $job->markFailed($e->getMessage(), $maxAttempts);
                $this->stderr("FAIL job #{$job->id} ({$job->type}): {$e->getMessage()}\n");
                Yii::error("SyncJob #{$job->id} failed: {$e->getMessage()}", __METHOD__);
            }

            // Rate limit only jobs that actually hit the AliExpress affiliate gateway; LLM (title) and
            // mtop (reviews) jobs go to other hosts and would just waste seconds sleeping here.
            if (SyncJobTypeEnum::from($job->type)->hitsAffiliateApi()) {
                sleep(random_int(2, 4));
            }
        }

        $this->stdout("Processed {$processed} job(s).\n");

        return ExitCode::OK;
    }
}
