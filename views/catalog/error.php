<?php
/** @var yii\web\View $this */
/** @var string $name */
/** @var string $message */
use yii\helpers\Html;
use yii\helpers\Url;
$this->title = $name;
?>
<div class="mx-auto max-w-xl py-16 text-center">
    <h1 class="text-3xl font-bold"><?= Html::encode($name) ?></h1>
    <p class="mt-3 text-gray-600"><?= nl2br(Html::encode($message)) ?></p>
    <a href="<?= Url::to(['/catalog/index']) ?>" class="btn-accent mt-6">Back to store</a>
</div>
