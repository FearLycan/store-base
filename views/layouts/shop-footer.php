<?php

/** @var yii\web\View $this */

use yii\helpers\Html;

$footer = (string)(Yii::$app->params['site.footer'] ?? '');
$name = (string)(Yii::$app->params['site.name'] ?? 'Store');
?>
<footer class="mt-12 border-t border-gray-200 bg-white">
    <div class="mx-auto max-w-7xl px-4 py-8 text-sm text-gray-500">
        <p><?= Html::encode($footer !== '' ? $footer : ('© ' . date('Y') . ' ' . $name)) ?></p>
        <p class="mt-2 text-xs">As an AliExpress affiliate we may earn from qualifying purchases. Prices and availability on AliExpress may differ.</p>
    </div>
</footer>
