<?php

declare(strict_types=1);

use yii\bootstrap5\ActiveForm;
use yii\helpers\Html;

/** @var yii\web\View $this */
/** @var app\modules\admin\models\AddStoreForm $model */

$this->title = 'Add store';
?>
<h1 class="h3 mb-3"><?= Html::encode($this->title) ?></h1>

<div class="row">
    <div class="col-lg-6">
        <?php $form = ActiveForm::begin(); ?>
        <?= $form->field($model, 'url')->textInput(['placeholder' => 'https://pl.aliexpress.com/store/1102516804']) ?>
        <?= $form->field($model, 'name')->textInput(['placeholder' => 'optional display name']) ?>
        <div class="mt-3">
            <?= Html::submitButton('Add &amp; queue discovery', ['class' => 'btn btn-primary']) ?>
            <?= Html::a('Cancel', ['index'], ['class' => 'btn btn-link']) ?>
        </div>
        <?php ActiveForm::end(); ?>
    </div>
</div>
