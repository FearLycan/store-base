<?php

declare(strict_types=1);

namespace app\commands;

use app\components\llm\NvidiaClient;
use app\components\llm\OllamaClient;
use app\components\llm\TitleRewriter;
use app\enums\SyncJobTypeEnum;
use app\models\Product;
use app\models\SyncJob;
use Throwable;
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

    /**
     * Queues TITLE_REWRITE jobs to humanise long marketplace titles (and publish drafts).
     * By default only products without a display_title; pass `--force=1` to regenerate all.
     * Process the queue afterwards with `php yii sync/process`.
     *
     * @param int|null $storeId only this store when given
     * @param int      $force   1 = re-rewrite products that already have a display_title
     */
    public function actionRewriteTitles(?int $storeId = null, int $force = 0, int $limit = 1000): int
    {
        $query = Product::find()->limit($limit);
        if ($storeId !== null) {
            $query->andWhere(['store_id' => $storeId]);
        }
        if ($force !== 1) {
            $query->andWhere(['or', ['display_title' => null], ['display_title' => '']]);
        }

        $queued = 0;
        foreach ($query->all() as $product) {
            SyncJob::enqueue(SyncJobTypeEnum::TITLE_REWRITE, $product->store_id, $product->id, ['external_id' => $product->external_id]);
            $queued++;
        }

        $this->stdout("Queued {$queued} title-rewrite job(s). Run `php yii sync/process` to execute.\n");

        return ExitCode::OK;
    }

    /** Messy marketplace titles used when the catalogue has no products to sample. */
    private const SAMPLE_TITLES = [
        '2024 New Fashion Men Casual Cotton Long Sleeve Slim Fit Business Shirt Plus Size S-5XL Breathable Tops',
        'LUXUSTEEL Men\'s Simple Hoops Earrings Black Color Stainless Steel Clip Earring for Men Women Rock Hiphop Circle Ear 2 Pieces',
        'For 14 Ultra Photo Kit Phone Case Filter Accessories B-ABLM',
        'BISAER 925 Sterling Silver Sparkling Heart Pendant Necklace for Women Wedding Engagement Fine Jewelry Gift HSN0123',
        'Hot Sale 1/2/4PCS Kitchen Storage Organizer Box Refrigerator Fresh-keeping Sealed Food Container Free Shipping',
        'ZORCVENS New Trendy Stainless Steel Cuban Link Chain Bracelet for Men Women Punk Hip Hop Jewelry 7/8/9 inch',
        'Portable Mini USB Rechargeable Handheld Fan 3 Speed Wind Adjustable Quiet Desk Fan for Office Home Travel Outdoor',
        '2023 Newest Wireless Bluetooth 5.3 Earbuds TWS HiFi Stereo Noise Cancelling Headphones with LED Display Charging Case',
        'Waterproof Dog Cat Pet Bed Mat Soft Warm Fleece Washable Removable Cover Small Medium Large Dogs S M L XL Multicolor',
        'Multifunctional Car Seat Gap Filler Organizer PU Leather Storage Box for Phone Keys Cards Coins Universal Auto Interior',
    ];

    /**
     * Side-by-side A/B test of the title rewriter on two LLM backends (Ollama vs NVIDIA).
     * Pulls real product titles (or falls back to built-in samples), runs each through both
     * providers and prints outputs + latency + a summary. Read-only; nothing is saved.
     *
     * Usage: `php yii product/compare-rewrite` (defaults to 10 titles).
     */
    public function actionCompareRewrite(int $limit = 10): int
    {
        $titles = array_values(array_filter(array_map(
            static fn (Product $p): string => trim((string)$p->title),
            Product::find()->select(['id', 'title'])->limit($limit)->all(),
        )));

        if ($titles === []) {
            $this->stdout("No products in catalogue — using built-in sample titles.\n");
            $titles = self::SAMPLE_TITLES;
        }
        $titles = array_slice($titles, 0, $limit);

        $ollama = new TitleRewriter(new OllamaClient());
        $nvidia = new TitleRewriter(new NvidiaClient());

        $sumOllama = 0.0;
        $sumNvidia = 0.0;
        $okOllama = 0;
        $okNvidia = 0;

        foreach ($titles as $i => $raw) {
            $n = $i + 1;
            $this->stdout("\n[{$n}] RAW: {$raw}\n");

            [$oText, $oMs, $oErr] = $this->timeRewrite($ollama, $raw);
            [$vText, $vMs, $vErr] = $this->timeRewrite($nvidia, $raw);

            $sumOllama += $oMs;
            $sumNvidia += $vMs;
            $okOllama += $oErr === null ? 1 : 0;
            $okNvidia += $vErr === null ? 1 : 0;

            $this->stdout(sprintf("    ollama (%6d ms): %s\n", $oMs, $oErr ?? $oText));
            $this->stdout(sprintf("    nvidia (%6d ms): %s\n", $vMs, $vErr ?? $vText));
        }

        $cnt = count($titles);
        $this->stdout(sprintf(
            "\n--- summary over %d titles ---\n"
            . "ollama (gpt-oss:120b)      : %d/%d ok, avg %d ms\n"
            . "nvidia (nemotron-3-super)  : %d/%d ok, avg %d ms\n",
            $cnt,
            $okOllama, $cnt, $cnt ? (int)round($sumOllama / $cnt) : 0,
            $okNvidia, $cnt, $cnt ? (int)round($sumNvidia / $cnt) : 0,
        ));

        return ExitCode::OK;
    }

    /** @return array{0:string,1:int,2:?string} [rewritten text, elapsed ms, error message or null] */
    private function timeRewrite(TitleRewriter $rewriter, string $raw): array
    {
        $start = microtime(true);
        try {
            $text = $rewriter->rewrite($raw);
            $err = null;
        } catch (Throwable $e) {
            $text = '';
            $err = 'ERR: ' . $e->getMessage();
        }
        $ms = (int)round((microtime(true) - $start) * 1000);

        return [$text, $ms, $err];
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
