<?php

declare(strict_types=1);

namespace app\assets;

use yii\web\AssetBundle;
use yii\web\View;

/**
 * Chart.js + its date-fns time-scale adapter. Registered only by views that draw
 * a chart (e.g. the product price-history chart), so it stays off other pages.
 * Vendored under web/libs/chartjs (no CDN).
 */
final class ChartAsset extends AssetBundle
{
    public $basePath = '@webroot';
    public $baseUrl = '@web';
    public $js = [
        'libs/chartjs/chart.umd.min.js',
        'libs/chartjs/chartjs-adapter-date-fns.bundle.min.js',
    ];
    public $jsOptions = ['defer' => true, 'position' => View::POS_END];
}
