<?php

declare(strict_types=1);

use app\models\Store;
use yii\bootstrap5\ActiveForm;
use yii\helpers\ArrayHelper;
use yii\helpers\Html;

/** @var yii\web\View $this */
/** @var app\modules\admin\models\ImportProductForm $model */

$this->title = 'Import products';
$stores = ArrayHelper::map(Store::find()->orderBy(['name' => SORT_ASC])->all(), 'id', 'name');
?>
<h1 class="h3 mb-3"><?= Html::encode($this->title) ?></h1>
<p class="text-muted">Paste AliExpress product URLs or numeric IDs, one per line. They are queued for import (price, category, affiliate link, reviews).</p>

<div class="row">
    <div class="col-lg-7">
        <?php $form = ActiveForm::begin(); ?>
        <?= $form->field($model, 'store_id')->dropDownList($stores, ['prompt' => '— select store —']) ?>
        <?= $form->field($model, 'urls')->textarea(['rows' => 8, 'placeholder' => "https://www.aliexpress.com/item/3256809685799868.html\n3256812116877307"]) ?>
        <div class="mt-3">
            <?= Html::submitButton('Queue import', ['class' => 'btn btn-primary']) ?>
            <?= Html::a('Cancel', ['index'], ['class' => 'btn btn-link']) ?>
        </div>
        <?php ActiveForm::end(); ?>
    </div>
</div>
