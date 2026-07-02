<?php

/** @var yii\web\View $this */

use app\models\Category;
use yii\helpers\Html;
use yii\helpers\Url;

$siteName = (string)(Yii::$app->params['site.name'] ?? 'Store');
$logo = (string)(Yii::$app->params['site.logo'] ?? '');
$topCategories = Category::find()->where(['level' => 1])->orderBy(['name' => SORT_ASC])->limit(8)->all();

// Sub-categories for the header dropdowns, fetched in one query and grouped by parent.
$childrenByParent = [];
$topIds = array_map(static fn (Category $c): int => $c->id, $topCategories);
if ($topIds !== []) {
    foreach (Category::find()->where(['parent_id' => $topIds])->orderBy(['name' => SORT_ASC])->all() as $sub) {
        $childrenByParent[$sub->parent_id][] = $sub;
    }
}

$q = (string)Yii::$app->request->get('q', '');
$caret = '<svg class="nav-caret" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m4 6 4 4 4-4"/></svg>';
?>
<header class="sticky top-0 z-30 border-b border-gray-200 bg-white">
    <div class="mx-auto flex max-w-7xl items-center gap-4 px-4 py-3">
        <a href="<?= Url::to(['/catalog/index']) ?>" class="flex flex-none items-center gap-2.5" aria-label="<?= Html::encode($siteName) ?> home">
            <?php if ($logo !== ''): ?>
                <img src="<?= Html::encode($logo) ?>" alt="" class="h-8">
                <span class="text-lg font-bold"><?= Html::encode($siteName) ?></span>
            <?php else: ?>
                <?= $this->render('_brand-logo', ['iconSize' => 36, 'wordmarkHeight' => 0]) ?>
                <span class="flex flex-col justify-center">
                    <span class="text-[10px] font-semibold leading-none tracking-[0.18em] text-gray-400">snag<span class="text-[color:var(--accent)]">loft</span></span>
                    <span class="mt-1 text-lg font-bold leading-none tracking-tight text-gray-900"><?= Html::encode($siteName) ?></span>
                </span>
            <?php endif; ?>
        </a>
        <form action="<?= Url::to(['/catalog/search']) ?>" method="get" class="relative flex-1" x-data>
            <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" viewBox="0 0 20 20" fill="none" aria-hidden="true">
                <circle cx="9" cy="9" r="6" stroke="currentColor" stroke-width="1.7"/>
                <path d="m13.5 13.5 3.5 3.5" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/>
            </svg>
            <input type="search" name="q" value="<?= Html::encode($q) ?>" placeholder="Search products…" autocomplete="off"
                   @focus="$dispatch('search-open', $el.value); $el.blur()"
                   class="w-full rounded-lg border border-gray-300 py-2 pl-9 pr-4 focus:border-[color:var(--accent)] focus:outline-none sm:pr-16">
            <kbd class="kbd absolute right-3 top-1/2 hidden -translate-y-1/2 sm:inline-flex" aria-hidden="true">Ctrl K</kbd>
        </form>
        <a href="<?= Url::to(['/catalog/all']) ?>" class="hidden text-sm text-gray-600 hover:text-[color:var(--accent)] sm:block">All products</a>
        <a href="<?= Url::to(['/catalog/wishlist']) ?>" x-data class="hdr-fav" aria-label="Wishlist">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.6l-1-1a5.5 5.5 0 0 0-7.8 7.8l1 1L12 21l7.8-7.6 1-1a5.5 5.5 0 0 0 0-7.8z"/></svg>
            <span x-show="$store.shop.favCount > 0" x-cloak class="hdr-fav-badge" x-text="$store.shop.favCount"></span>
        </a>
    </div>
    <nav class="mx-auto max-w-7xl overflow-x-auto px-4 pb-2 text-sm md:overflow-visible">
        <ul class="flex gap-1">
            <?php foreach ($topCategories as $cat): ?>
                <?php $subs = $childrenByParent[$cat->id] ?? []; ?>
                <li class="nav-item relative">
                    <a class="nav-link" href="<?= Url::to(['/catalog/category', 'slug' => $cat->slug]) ?>">
                        <?= Html::encode($cat->name) ?><?php if ($subs !== []): ?><?= $caret ?><?php endif; ?>
                    </a>
                    <?php if ($subs !== []): ?>
                    <div class="nav-dropdown">
                        <a class="nav-dropdown-link nav-dropdown-all" href="<?= Url::to(['/catalog/category', 'slug' => $cat->slug]) ?>">All <?= Html::encode($cat->name) ?></a>
                        <div class="nav-dropdown-sep"></div>
                        <?php foreach ($subs as $sub): ?>
                            <a class="nav-dropdown-link" href="<?= Url::to(['/catalog/category', 'slug' => $sub->slug]) ?>"><?= Html::encode($sub->name) ?></a>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
    </nav>
</header>
