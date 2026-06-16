<?php
/** @var app\models\Product $product */
use yii\helpers\Html;

$drop = $product->priceDropAmount();
if ($drop === null) { return; }
?>
<span class="inline-flex items-center gap-0.5 rounded bg-emerald-50 px-1.5 py-0.5 text-[11px] font-bold text-emerald-700" title="Price dropped recently">
    <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-3 w-3" aria-hidden="true"><path d="M8 3v10M4 9l4 4 4-4"/></svg>
    <span class="tabular-nums"><?= Html::encode(number_format($drop / 100, 2)) ?></span>
</span>
