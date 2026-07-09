<?php
/**
 * "Popular categories" cards: the store's biggest categories, fronted by the
 * store's best-selling product photo in each. Click applies that category
 * filter on the store page.
 *
 * @var yii\web\View $this
 * @var app\models\Store $store
 * @var array<int, array{category: app\models\Category, image: string|null, count: int}> $topCategories
 */

use yii\helpers\Html;
use yii\helpers\Url;
?>
<section class="mb-12">
    <div class="mb-4 flex items-baseline justify-between gap-4">
        <h2 class="text-xl font-bold tracking-tight">Popular categories</h2>
    </div>
    <div class="store-cat-grid">
        <?php foreach ($topCategories as $cover): ?>
            <?php $cat = $cover['category']; ?>
            <a href="<?= Url::to(['/store/view', 'slug' => $store->slug, 'category' => $cat->slug ?: $cat->id]) ?>" class="store-cat-card">
                <span class="store-cat-media">
                    <?php if ($cover['image'] !== null): ?>
                        <img src="<?= Html::encode($cover['image']) ?>" alt="" loading="lazy" decoding="async" class="store-cat-img">
                    <?php else: ?>
                        <span class="store-cat-fallback" aria-hidden="true"></span>
                    <?php endif; ?>
                    <span class="store-cat-scrim" aria-hidden="true"></span>
                    <span class="store-cat-caption">
                        <span class="store-cat-name"><?= Html::encode($cat->name) ?></span>
                        <span class="store-cat-count"><?= number_format($cover['count']) ?> <?= $cover['count'] === 1 ? 'product' : 'products' ?></span>
                    </span>
                </span>
            </a>
        <?php endforeach; ?>
    </div>
</section>
