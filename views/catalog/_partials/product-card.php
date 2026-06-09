<?php
/** @var app\models\Product $product */
use yii\helpers\Html;
use yii\helpers\Url;
$img = $product->main_image ?: '/img/placeholder.png';
$href = Url::to(['/product/view', 'slug' => $product->slug]);
?>
<a href="<?= $href ?>" class="group block overflow-hidden rounded-xl border border-gray-200 bg-white transition hover:shadow-md">
    <div class="aspect-square overflow-hidden bg-gray-100">
        <img src="<?= Html::encode($img) ?>" alt="<?= Html::encode((string)$product->title) ?>" loading="lazy"
             class="h-full w-full object-cover transition duration-300 group-hover:scale-105">
    </div>
    <div class="p-3">
        <h3 class="line-clamp-2 min-h-[2.5rem] text-sm text-gray-800"><?= Html::encode((string)$product->title) ?></h3>
        <div class="mt-2"><?= $this->render('price', ['product' => $product]) ?></div>
        <div class="mt-1"><?= $this->render('stars', ['value' => $product->rating_value, 'count' => $product->review_count]) ?></div>
    </div>
</a>
