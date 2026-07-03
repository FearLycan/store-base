<?php

declare(strict_types=1);

use yii\bootstrap5\ActiveForm;
use yii\helpers\Html;

/** @var yii\web\View $this */
/** @var app\modules\admin\models\EditStoreForm $model */
/** @var app\models\Store $store */

$this->title = 'Edit store: ' . $store->name;
?>
<h1 class="h3 mb-3"><?= Html::encode($this->title) ?></h1>

<div class="row">
    <div class="col-lg-6">
        <?php $form = ActiveForm::begin(); ?>
        <?= $form->field($model, 'name')->textInput() ?>
        <?= $form->field($model, 'image_url')->textInput(['placeholder' => 'https://…/logo.png'])->hint('Shown on the storefront store page and product "Sold by" block.') ?>
        <?php if (trim((string) $store->image_url) !== ''): ?>
            <div class="mb-3">
                <img src="<?= Html::encode((string) $store->image_url) ?>" alt="" class="rounded border" style="height:80px;width:80px;object-fit:cover;">
            </div>
        <?php endif; ?>
        <div class="mt-3">
            <?= Html::submitButton('Save', ['class' => 'btn btn-primary']) ?>
            <?= Html::a('Cancel', ['view', 'id' => $store->id], ['class' => 'btn btn-link']) ?>
        </div>
        <?php ActiveForm::end(); ?>
    </div>
</div>
