<?php

/** @var yii\web\View $this */

use app\models\Category;
use yii\helpers\Html;
use yii\helpers\Url;

$siteName = (string)(Yii::$app->params['site.name'] ?? 'Store');
$logo = (string)(Yii::$app->params['site.logo'] ?? '');
$topCategories = Category::find()->where(['level' => 1])->orderBy(['name' => SORT_ASC])->limit(8)->all();
$q = (string)Yii::$app->request->get('q', '');
?>
<header class="sticky top-0 z-30 border-b border-gray-200 bg-white">
    <div class="mx-auto flex max-w-7xl items-center gap-4 px-4 py-3">
        <a href="<?= Url::to(['/catalog/index']) ?>" class="flex items-center gap-2 text-lg font-bold">
            <?php if ($logo !== ''): ?><img src="<?= Html::encode($logo) ?>" alt="" class="h-8"><?php endif; ?>
            <span><?= Html::encode($siteName) ?></span>
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
    </div>
    <nav class="mx-auto max-w-7xl overflow-x-auto px-4 pb-2">
        <ul class="flex gap-4 text-sm text-gray-600">
            <?php foreach ($topCategories as $cat): ?>
                <li><a class="whitespace-nowrap hover:text-[color:var(--accent)]"
                       href="<?= Url::to(['/catalog/category', 'slug' => $cat->slug]) ?>"><?= Html::encode($cat->name) ?></a></li>
            <?php endforeach; ?>
        </ul>
    </nav>
</header>
