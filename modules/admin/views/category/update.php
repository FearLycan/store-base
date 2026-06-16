<?php

declare(strict_types=1);

use yii\bootstrap5\ActiveForm;
use yii\helpers\Html;

/** @var yii\web\View $this */
/** @var app\models\Category $category */
/** @var app\modules\admin\models\CategoryImageForm $form */

$this->title = 'Edit image · ' . $category->name;
$hasImage = (string)$category->image_url !== '';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h3 mb-0">
        <?= Html::encode($category->name) ?>
        <?= $category->level === 1
            ? '<span class="badge text-bg-primary align-middle">top-level</span>'
            : '<span class="badge text-bg-secondary align-middle">sub</span>' ?>
    </h1>
    <?= Html::a('← Back to categories', ['index'], ['class' => 'btn btn-link']) ?>
</div>

<div class="row g-4">
    <div class="col-lg-5">
        <div class="card">
            <div class="card-header py-2 small text-secondary">Current cover</div>
            <div class="card-body">
                <?php if ($hasImage): ?>
                    <?= Html::img($category->image_url, [
                        'class' => 'img-fluid rounded',
                        'style' => 'max-height:260px;object-fit:cover;',
                        'alt' => '',
                    ]) ?>
                    <p class="text-secondary small mt-2 mb-0 text-break"><?= Html::encode($category->image_url) ?></p>
                <?php else: ?>
                    <div class="text-secondary">
                        <p class="mb-1"><strong>No custom image.</strong></p>
                        <p class="mb-0 small">The storefront tile shows this category's best-selling product photo automatically.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-lg-7">
        <?php $f = ActiveForm::begin(['options' => ['enctype' => 'multipart/form-data']]); ?>

        <?= $f->field($form, 'file')->fileInput(['accept' => 'image/*'])
            ->hint('PNG, JPG, WEBP or GIF · up to 4&nbsp;MB.') ?>

        <?= $f->field($form, 'imageUrl')->textInput(['placeholder' => 'https://example.com/cover.jpg'])
            ->hint('Used only when no file is uploaded.') ?>

        <?php if ($hasImage): ?>
            <?= $f->field($form, 'remove')->checkbox() ?>
        <?php endif; ?>

        <div class="mt-3">
            <?= Html::submitButton('Save image', ['class' => 'btn btn-primary']) ?>
            <?= Html::a('Cancel', ['index'], ['class' => 'btn btn-link']) ?>
        </div>

        <?php ActiveForm::end(); ?>
    </div>
</div>
