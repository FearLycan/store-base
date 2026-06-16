<?php
/** @var array $current Current query params (filters in effect). */
/** @var app\models\Category[] $categories Top-level categories for the category select. */
/** @var bool $showCategory Whether to render the category select (hidden on category pages — already scoped). */
/** @var string $action Form action URL; '' submits to the current page (used on the home page to jump into /catalog/all). */
use yii\helpers\Html;
use yii\helpers\Url;

$categories   = $categories ?? [];
$showCategory = $showCategory ?? false;
$action       = $action ?? '';

$sortLabels = [
    'popular'    => 'Most popular',
    'newest'     => 'Newest',
    'price_asc'  => 'Price: low to high',
    'price_desc' => 'Price: high to low',
    'rating'     => 'Top rated',
];
$ratingLabels = ['' => 'Any rating', '4.5' => '4.5 ★ & up', '4' => '4 ★ & up', '3' => '3 ★ & up'];

$cur = static fn (string $k, string $d = ''): string => isset($current[$k]) ? (string)$current[$k] : $d;
$on  = static fn (string $k): bool => !empty($current[$k]);

// Reset is offered only when a narrowing filter is in effect (sort alone doesn't count).
$hasActive = false;
foreach (['min', 'max', 'rating', 'category', 'sale', 'video'] as $k) {
    if (isset($current[$k]) && $current[$k] !== '') { $hasActive = true; break; }
}
$resetUrl = Url::current(['min' => null, 'max' => null, 'rating' => null, 'category' => null, 'sale' => null, 'video' => null, 'sort' => null, 'page' => null]);

$check = '<svg viewBox="0 0 12 12" class="h-3 w-3" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m2.5 6 2.5 2.5 4.5-5"/></svg>';
?>
<form method="get" action="<?= Html::encode($action) ?>" class="filter-bar mb-6 flex flex-wrap items-center gap-2.5 rounded-2xl bg-white p-3 sm:p-3.5">
    <?php if ($cur('q') !== ''): ?><input type="hidden" name="q" value="<?= Html::encode($cur('q')) ?>"><?php endif; ?>

    <select name="sort" class="filter-select" aria-label="Sort by" onchange="this.form.requestSubmit()">
        <?php foreach ($sortLabels as $val => $label): ?>
            <option value="<?= $val ?>"<?= $cur('sort', 'popular') === (string)$val ? ' selected' : '' ?>><?= $label ?></option>
        <?php endforeach; ?>
    </select>

    <div class="filter-price">
        <span class="text-gray-400">$</span>
        <input type="number" inputmode="decimal" step="0.01" min="0" name="min" value="<?= Html::encode($cur('min')) ?>" placeholder="Min" aria-label="Minimum price">
        <span class="px-0.5 text-gray-300">–</span>
        <span class="text-gray-400">$</span>
        <input type="number" inputmode="decimal" step="0.01" min="0" name="max" value="<?= Html::encode($cur('max')) ?>" placeholder="Max" aria-label="Maximum price">
    </div>

    <select name="rating" class="filter-select" aria-label="Minimum rating" onchange="this.form.requestSubmit()">
        <?php foreach ($ratingLabels as $val => $label): ?>
            <option value="<?= $val ?>"<?= $cur('rating') === (string)$val ? ' selected' : '' ?>><?= $label ?></option>
        <?php endforeach; ?>
    </select>

    <?php if ($showCategory && $categories !== []): ?>
    <select name="category" class="filter-select" aria-label="Category" onchange="this.form.requestSubmit()">
        <option value="">All categories</option>
        <?php foreach ($categories as $c): ?>
            <option value="<?= $c->id ?>"<?= $cur('category') === (string)$c->id ? ' selected' : '' ?>><?= Html::encode($c->name) ?></option>
        <?php endforeach; ?>
    </select>
    <?php endif; ?>

    <label class="filter-toggle">
        <input type="checkbox" name="sale" value="1"<?= $on('sale') ? ' checked' : '' ?> onchange="this.form.requestSubmit()">
        <span class="filter-dot"><?= $check ?></span>
        <span>On sale</span>
    </label>

    <label class="filter-toggle">
        <input type="checkbox" name="video" value="1"<?= $on('video') ? ' checked' : '' ?> onchange="this.form.requestSubmit()">
        <span class="filter-dot"><?= $check ?></span>
        <span>Has video</span>
    </label>

    <button type="submit" class="filter-apply">Apply</button>

    <?php if ($hasActive): ?>
    <a href="<?= $resetUrl ?>" class="filter-reset ml-auto" rel="nofollow">
        <svg viewBox="0 0 24 24" class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 6 18 18M18 6 6 18"/></svg>
        Clear
    </a>
    <?php endif; ?>
</form>
