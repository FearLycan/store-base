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
        <form action="<?= Url::to(['/catalog/search']) ?>" method="get" class="flex-1">
            <input type="search" name="q" value="<?= Html::encode($q) ?>" placeholder="Search products…"
                   class="w-full rounded-lg border border-gray-300 px-4 py-2 focus:border-[color:var(--accent)] focus:outline-none">
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
