<?php
/** @var array{category: app\models\Category, image: string|null, count: int} $cover */
use yii\helpers\Html;
use yii\helpers\Url;

$cat   = $cover['category'];
$img   = $cover['image'];
$count = (int)$cover['count'];
$href  = Url::to(['/catalog/category', 'slug' => $cat->slug]);
?>
<a href="<?= $href ?>" class="cat-chip">
    <span class="cat-chip-media">
        <?php if ($img !== null): ?>
            <img src="<?= Html::encode($img) ?>" alt="" loading="lazy" decoding="async" class="cat-chip-img">
        <?php else: ?>
            <span class="cat-chip-fallback" aria-hidden="true"></span>
        <?php endif; ?>
    </span>
    <span class="cat-chip-name"><?= Html::encode($cat->name) ?></span>
    <span class="cat-chip-count"><?= number_format($count) ?> <?= $count === 1 ? 'product' : 'products' ?></span>
</a>
