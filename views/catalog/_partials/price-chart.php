<?php
/** @var yii\web\View $this */
/** @var app\models\Product $product */
use app\assets\ChartAsset;
use yii\helpers\Html;
use yii\helpers\Json;

// Build the price series from recorded history (ascending), then extend it to
// "now" at the current price so the latest level reaches today. Need >= 2 points.
$points = [];
foreach ($product->priceHistory as $h) {
    if ($h->price === null) {
        continue;
    }
    $points[] = ['x' => (int) $h->recorded_at * 1000, 'y' => round($h->price / 100, 2)];
}
if ($product->price !== null) {
    $points[] = ['x' => time() * 1000, 'y' => round($product->price / 100, 2)];
}
if (count($points) < 2) {
    return;
}

// All-time stats (range toggles only change the chart window, not these).
$prices  = array_column($points, 'y');
$min     = min($prices);
$max     = max($prices);
$current = end($prices);
$isLowest = $current <= $min + 0.0001;
$currency = $product->currency_code ?: 'USD';
$fmt = static fn (float $v): string => $currency . ' ' . number_format($v, 2);

ChartAsset::register($this);
$canvasId = 'price-chart-' . (int) $product->id;
$cfg = Json::encode(['points' => $points, 'currency' => $currency]);

// Chart.js is deferred (ChartAsset) so it has executed by DOMContentLoaded.
// Plain JS (not Alpine: Alpine boots before the deferred Chart script runs).
$this->registerJs(<<<JS
document.addEventListener('DOMContentLoaded', function () {
    var el = document.getElementById('$canvasId');
    if (!el || typeof Chart === 'undefined') { return; }
    var cfg = $cfg;
    var accent = (getComputedStyle(document.documentElement).getPropertyValue('--accent') || '#2563eb').trim();
    var chart = new Chart(el, {
        type: 'line',
        data: { datasets: [{
            label: 'Price',
            data: cfg.points,
            stepped: true,
            borderColor: accent,
            backgroundColor: 'color-mix(in srgb, ' + accent + ' 12%, transparent)',
            borderWidth: 2,
            pointRadius: 0,
            pointHoverRadius: 4,
            fill: true,
        }] },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            scales: {
                x: { type: 'time', time: { unit: 'day', tooltipFormat: 'MMM d, yyyy' }, grid: { display: false }, ticks: { maxRotation: 0, autoSkip: true, maxTicksLimit: 6 } },
                y: { beginAtZero: true, grid: { color: 'rgba(17,24,39,0.06)' }, ticks: { callback: function (v) { return cfg.currency + ' ' + v; } } }
            },
            plugins: {
                legend: { display: false },
                tooltip: { callbacks: { label: function (c) { return cfg.currency + ' ' + c.parsed.y.toFixed(2); } } }
            }
        }
    });

    var wrap = el.closest('[data-price-chart]');
    var btns = wrap.querySelectorAll('.pc-range-btn');
    var spans = { '1m': 30, '3m': 90, '6m': 180, '1y': 365 };
    function apply(r) {
        var now = Date.now();
        chart.options.scales.x.min = (r === 'all') ? undefined : now - spans[r] * 86400000;
        chart.options.scales.x.max = now;
        chart.update();
        btns.forEach(function (b) { b.classList.toggle('is-active', b.dataset.range === r); });
    }
    btns.forEach(function (b) { b.addEventListener('click', function () { apply(b.dataset.range); }); });
    apply('all');
});
JS, \yii\web\View::POS_END);
?>
<section class="mt-10" data-price-chart>
    <div class="rounded-2xl border border-gray-200 bg-white p-5">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <p class="text-[11px] font-semibold uppercase tracking-wider text-gray-400">Price history</p>
                <div class="mt-1 flex items-center gap-2">
                    <span class="text-2xl font-bold tabular-nums text-gray-900"><?= Html::encode($fmt($current)) ?></span>
                    <?php if ($isLowest): ?>
                    <span class="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2 py-0.5 text-xs font-semibold text-emerald-700">
                        <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-3 w-3" aria-hidden="true"><path d="M8 3v10M4 9l4 4 4-4"/></svg>
                        Lowest ever
                    </span>
                    <?php endif; ?>
                </div>
            </div>
            <div class="inline-flex flex-none rounded-lg border border-gray-200 bg-gray-50 p-0.5 text-xs font-semibold" role="group" aria-label="Chart range">
                <button type="button" class="pc-range-btn" data-range="1m">1M</button>
                <button type="button" class="pc-range-btn" data-range="3m">3M</button>
                <button type="button" class="pc-range-btn" data-range="6m">6M</button>
                <button type="button" class="pc-range-btn" data-range="1y">1Y</button>
                <button type="button" class="pc-range-btn is-active" data-range="all">All</button>
            </div>
        </div>

        <div class="relative mt-4 h-64">
            <canvas id="<?= $canvasId ?>"></canvas>
        </div>

        <div class="mt-4 grid grid-cols-3 gap-px overflow-hidden rounded-xl border border-gray-200 bg-gray-200 text-center">
            <div class="bg-white px-3 py-3">
                <p class="text-[10px] font-semibold uppercase tracking-wider text-gray-400">Lowest</p>
                <p class="mt-0.5 font-bold tabular-nums text-emerald-700"><?= Html::encode($fmt($min)) ?></p>
            </div>
            <div class="bg-white px-3 py-3">
                <p class="text-[10px] font-semibold uppercase tracking-wider text-gray-400">Highest</p>
                <p class="mt-0.5 font-bold tabular-nums text-gray-900"><?= Html::encode($fmt($max)) ?></p>
            </div>
            <div class="bg-white px-3 py-3">
                <p class="text-[10px] font-semibold uppercase tracking-wider text-gray-400">Current</p>
                <p class="mt-0.5 font-bold tabular-nums text-gray-900"><?= Html::encode($fmt($current)) ?></p>
            </div>
        </div>
    </div>
</section>
