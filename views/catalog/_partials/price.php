<?php
/** @var app\models\Product $product */
use yii\helpers\Html;
?>
<?php if ($product->price !== null): ?>
    <span class="text-lg font-bold tabular-nums"><?= Html::encode(number_format($product->price / 100, 2)) ?> <?= Html::encode($product->currency_code ?: 'USD') ?></span>
    <?php if ($product->original_price !== null && $product->original_price > $product->price): ?>
        <span class="ml-1 text-sm text-gray-400 line-through tabular-nums"><?= Html::encode(number_format($product->original_price / 100, 2)) ?></span>
    <?php endif; ?>
<?php else: ?>
    <span class="text-sm font-semibold text-[color:var(--accent)]">Check price on AliExpress</span>
<?php endif; ?>
