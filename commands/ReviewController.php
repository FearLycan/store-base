<?php

declare(strict_types=1);

namespace app\commands;

use app\components\aliexpress\AliExpressReviewScraper;
use app\components\ReviewCache;
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
    /** @var bool|string Set via --apply on review/clean to write changes (default: dry run). */
    public $apply = false;

    public function options($actionID): array
    {
        $options = parent::options($actionID);
        if ($actionID === 'clean') {
            $options[] = 'apply';
        }

        return $options;
    }

    /**
     * Clears the isolated review cache (@runtime/review-cache). This cache is deliberately
     * invisible to `cache/flush-all`, so it must be flushed explicitly here.
     */
    public function actionFlushCache(): int
    {
        ReviewCache::get()->flush();
        $this->stdout("review cache flushed\n");

        return ExitCode::OK;
    }

    /**
     * Repopulates the full review aggregates (impression ids, real total, star
     * distribution, photo count + strip) for existing products by re-reading AE, so
     * the reviews UI shows full-corpus numbers instead of the stored sample.
     */
    public function actionBackfillImpressions(): int
    {
        $scraper = new AliExpressReviewScraper();
        foreach (Product::find()->where(['not', ['external_id' => null]])->each() as $p) {
            try {
                $agg = $scraper->fetchAggregates($p->external_id);
                $p->review_impressions  = $agg['impressions'] !== [] ? $agg['impressions'] : null;
                $p->review_total        = $agg['total'] ?: null;
                $p->review_rating_dist  = array_sum($agg['dist']) > 0 ? $agg['dist'] : null;
                $p->review_image_count  = $agg['imageCount'];
                $p->review_photos       = $agg['photos'] !== [] ? $agg['photos'] : null;
                $p->save(false, ['review_impressions', 'review_total', 'review_rating_dist', 'review_image_count', 'review_photos']);
                $this->stdout("ok {$p->slug}\n");
                usleep(500000);
            } catch (\Throwable $e) {
                $this->stderr("skip {$p->slug}: {$e->getMessage()}\n");
            }
        }

        return ExitCode::OK;
    }

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

    /**
     * Removes duplicate reviews left by earlier syncs and recomputes review_count.
     *
     * AliExpress returns the same review under shifting external ids, so the old
     * id-only dedupe let re-syncs insert near-identical copies. This collapses each
     * group down to one row (keeping the lowest id), prunes blank image rows, and
     * refreshes product.review_count so the displayed counter stays truthful.
     *
     * Rating-only "blank" reviews (no text, no photo) are left in place on purpose —
     * they still back the AliExpress rating aggregate; the product page just hides
     * them. Runs as a dry run by default; pass --apply to write changes.
     *
     *   php yii review/clean            # report only
     *   php yii review/clean --apply    # delete + recompute
     */
    public function actionClean(): int
    {
        $apply = filter_var($this->apply, FILTER_VALIDATE_BOOLEAN);
        $db = Yii::$app->db;

        // Group on the stable fingerprint (product + author + country + date + content)
        // and mark every row except the lowest id in each group for deletion.
        $dupRows = $db->createCommand(
            'SELECT r.id AS id, r.product_id AS product_id
               FROM product_review r
               JOIN (
                    SELECT MIN(id) AS keep_id, product_id, author_name, reviewer_country, reviewed_at, content
                      FROM product_review
                     GROUP BY product_id, author_name, reviewer_country, reviewed_at, content
                    HAVING COUNT(*) > 1
               ) g
                 ON g.product_id = r.product_id
                AND r.id <> g.keep_id
                AND (r.author_name      <=> g.author_name)
                AND (r.reviewer_country <=> g.reviewer_country)
                AND (r.reviewed_at      <=> g.reviewed_at)
                AND (r.content          <=> g.content)'
        )->queryAll();

        $dupIds   = array_map(static fn (array $r): int => (int)$r['id'], $dupRows);
        $affected = array_values(array_unique(array_map(static fn (array $r): int => (int)$r['product_id'], $dupRows)));

        $emptyImgCount = (int)$db->createCommand(
            "SELECT COUNT(*) FROM product_review_image WHERE url IS NULL OR TRIM(url) = ''"
        )->queryScalar();

        $this->stdout(sprintf(
            "Duplicate review rows: %d (across %d product(s))\nBlank image rows:      %d\n",
            count($dupIds), count($affected), $emptyImgCount,
        ));

        if (!$apply) {
            $this->stdout("\nDry run — nothing changed. Re-run with --apply to delete.\n");
            return ExitCode::OK;
        }

        if ($dupIds === [] && $emptyImgCount === 0) {
            $this->stdout("Nothing to clean.\n");
            return ExitCode::OK;
        }

        $tx = $db->beginTransaction();
        try {
            foreach (array_chunk($dupIds, 500) as $chunk) {
                $db->createCommand()->delete('product_review_image', ['review_id' => $chunk])->execute();
                $db->createCommand()->delete('product_review', ['id' => $chunk])->execute();
            }
            if ($emptyImgCount > 0) {
                $db->createCommand()->delete('product_review_image', ['or', ['url' => null], "TRIM(url) = ''"])->execute();
            }
            // Keep the counter in step with the rows that survive.
            foreach ($affected as $productId) {
                $count = (int)(new \yii\db\Query())
                    ->from('product_review')
                    ->where(['product_id' => $productId])
                    ->count('*', $db);
                $db->createCommand()->update('product', ['review_count' => $count], ['id' => $productId])->execute();
            }
            $tx->commit();
        } catch (\Throwable $e) {
            $tx->rollBack();
            $this->stderr('Failed: ' . $e->getMessage() . "\n");
            return ExitCode::UNSPECIFIED_ERROR;
        }

        $this->stdout(sprintf(
            "Deleted %d duplicate review(s) and %d blank image row(s); recomputed review_count for %d product(s).\n",
            count($dupIds), $emptyImgCount, count($affected),
        ));

        return ExitCode::OK;
    }
}
