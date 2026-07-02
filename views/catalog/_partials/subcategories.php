<?php
/** @var app\models\Category $category Current category being viewed. */
/** @var app\models\Category $branch Category the "All" chip points to (heading of the chip group). */
/** @var app\models\Category[] $children Sibling/child categories to offer as chips. */
use yii\helpers\Html;
use yii\helpers\Url;

if ($children === []) {
    return;
}
?>
<nav class="mb-6 flex flex-wrap gap-2" aria-label="Sub-categories">
    <a href="<?= Url::to(['/catalog/category', 'slug' => $branch->slug]) ?>"
       class="subcat-chip<?= $category->id === $branch->id ? ' is-active' : '' ?>">All <?= Html::encode($branch->name) ?></a>
    <?php foreach ($children as $child): ?>
        <a href="<?= Url::to(['/catalog/category', 'slug' => $child->slug]) ?>"
           class="subcat-chip<?= $category->id === $child->id ? ' is-active' : '' ?>"><?= Html::encode($child->name) ?></a>
    <?php endforeach; ?>
</nav>
