<?php
/** @var yii\web\View $this */
/** @var app\models\Product $product */
use app\assets\ChartAsset;
use yii\helpers\Json;

// Build the price series from recorded history (sorted ascending), then extend it
// to "now" at the current price so the latest level reaches today. Need >= 2 points.
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

ChartAsset::register($this);
$canvasId = 'price-chart-' . (int) $product->id;
$currency = $product->currency_code ?: 'USD';
$cfg = Json::encode(['points' => $points, 'currency' => $currency]);

// Chart.js is deferred (ChartAsset); init on DOMContentLoaded so it has executed.
$this->registerJs(<<<JS
document.addEventListener('DOMContentLoaded', function () {
    var el = document.getElementById('$canvasId');
    if (!el || typeof Chart === 'undefined') { return; }
    var cfg = $cfg;
    var accent = (getComputedStyle(document.documentElement).getPropertyValue('--accent') || '#2563eb').trim();
    new Chart(el, {
        type: 'line',
        data: { datasets: [{
            label: 'Price',
            data: cfg.points,
            stepped: true,
            borderColor: accent,
            backgroundColor: 'color-mix(in srgb, ' + accent + ' 10%, transparent)',
            borderWidth: 2,
            pointRadius: 2,
            pointHoverRadius: 4,
            fill: true,
        }] },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            scales: {
                x: { type: 'time', time: { unit: 'day', tooltipFormat: 'MMM d, yyyy' }, grid: { display: false }, ticks: { maxRotation: 0, autoSkip: true, maxTicksLimit: 6 } },
                y: { grid: { color: 'rgba(17,24,39,0.06)' }, ticks: { callback: function (v) { return cfg.currency + ' ' + v; } } }
            },
            plugins: {
                legend: { display: false },
                tooltip: { callbacks: { label: function (c) { return cfg.currency + ' ' + c.parsed.y.toFixed(2); } } }
            }
        }
    });
});
JS, \yii\web\View::POS_END);
?>
<section class="mt-10">
    <h2 class="mb-4 text-xl font-bold">Price history</h2>
    <div class="rounded-xl border border-gray-200 bg-white p-4">
        <div class="relative h-64">
            <canvas id="<?= $canvasId ?>"></canvas>
        </div>
    </div>
</section>
