<?php
/** @var yii\web\View $this */
/** @var array $current Active query params on this listing page. */
/** @var int $total Total matching products (dataProvider->totalCount). */
/** @var app\models\Category[] $categories Top categories, to label the category chip (optional). */
/** @var app\models\Store[] $stores Stores, to label the store chip (optional). */
use yii\helpers\Html;
use yii\helpers\Url;

$categories = $categories ?? [];
$stores = $stores ?? [];
$cur = static fn (string $k): string => isset($current[$k]) ? trim((string)$current[$k]) : '';

// Resolve the category chip's label from its id (only present on /catalog & /search).
$catName = '';
if ($cur('category') !== '') {
    foreach ($categories as $c) {
        if ((string)$c->id === $cur('category')) { $catName = (string)$c->name; break; }
    }
}

// Resolve the store chip's label from its id.
$storeName = '';
if ($cur('store') !== '') {
    foreach ($stores as $s) {
        if ((string)$s->id === $cur('store')) { $storeName = (string)$s->name; break; }
    }
}

$money = static fn (string $v): string => '$' . rtrim(rtrim(number_format((float)$v, 2), '0'), '.');

// [label, paramsToRemove]. Price min/max collapse into one range chip.
$chips = [];
if ($cur('q') !== '')        { $chips[] = ['“' . $cur('q') . '”', ['q' => null]]; }
if ($cur('category') !== '') { $chips[] = [$catName !== '' ? $catName : 'Category', ['category' => null]]; }
if ($cur('store') !== '')    { $chips[] = [$storeName !== '' ? $storeName : 'Store', ['store' => null]]; }
if ($cur('min') !== '' || $cur('max') !== '') {
    if ($cur('min') !== '' && $cur('max') !== '') { $priceLabel = $money($cur('min')) . '–' . $money($cur('max')); }
    elseif ($cur('min') !== '')                   { $priceLabel = $money($cur('min')) . '+'; }
    else                                          { $priceLabel = '≤ ' . $money($cur('max')); }
    $chips[] = [$priceLabel, ['min' => null, 'max' => null]];
}
if ($cur('rating') !== '')   { $chips[] = [$cur('rating') . '★ & up', ['rating' => null]]; }
if ($cur('sale') !== '')     { $chips[] = ['On sale', ['sale' => null]]; }
if ($cur('video') !== '')    { $chips[] = ['Has video', ['video' => null]]; }

// Empty catalog with no filters in effect — render nothing.
if ($total === 0 && $chips === []) { return; }

$clearUrl = Url::current(['q' => null, 'min' => null, 'max' => null, 'rating' => null, 'category' => null, 'store' => null, 'sale' => null, 'video' => null, 'page' => null]);
$x = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 6 18 18M18 6 6 18"/></svg>';
?>
<div class="mb-5 flex flex-wrap items-center gap-2">
    <span class="text-sm tabular-nums text-gray-500"><?= number_format($total) ?> product<?= $total === 1 ? '' : 's' ?></span>
    <?php if ($chips !== []): ?>
        <span class="mx-1 hidden h-4 w-px bg-gray-200 sm:block"></span>
        <?php foreach ($chips as [$label, $remove]): ?>
            <a href="<?= Url::current($remove + ['page' => null]) ?>" rel="nofollow" class="active-chip">
                <span><?= Html::encode($label) ?></span>
                <?= $x ?>
            </a>
        <?php endforeach; ?>
        <a href="<?= $clearUrl ?>" rel="nofollow" class="active-chip-clear">Clear all</a>
    <?php endif; ?>
</div>
