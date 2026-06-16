<?php
/** @var array{category: app\models\Category, image: string|null, count: int} $cover */
use yii\helpers\Html;
use yii\helpers\Url;

$cat   = $cover['category'];
$img   = $cover['image'];
$count = (int)$cover['count'];
$href  = Url::to(['/catalog/category', 'slug' => $cat->slug]);
?>
<a href="<?= $href ?>" class="cat-tile group">
    <?php if ($img !== null): ?>
        <img src="<?= Html::encode($img) ?>" alt="" loading="lazy" decoding="async" class="cat-tile-img">
    <?php else: ?>
        <span class="cat-tile-fallback" aria-hidden="true"></span>
    <?php endif; ?>
    <span class="cat-tile-scrim" aria-hidden="true"></span>
    <span class="cat-tile-body">
        <span class="cat-tile-name"><?= Html::encode($cat->name) ?></span>
        <span class="cat-tile-count"><?= number_format($count) ?> <?= $count === 1 ? 'product' : 'products' ?></span>
    </span>
    <span class="cat-tile-arrow" aria-hidden="true">
        <svg viewBox="0 0 16 16" fill="none" class="h-4 w-4"><path d="M6 3.5 10.5 8 6 12.5" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg>
    </span>
</a>
