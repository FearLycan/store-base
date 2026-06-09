<?php

declare(strict_types=1);

use app\models\Product;
use yii\grid\GridView;
use yii\helpers\Html;

/** @var yii\web\View $this */
/** @var yii\data\ActiveDataProvider $dataProvider */
/** @var int|null $storeId */

$this->title = 'Products';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h3 mb-0"><?= Html::encode($this->title) ?></h1>
    <?= Html::a('Import products', ['import'], ['class' => 'btn btn-primary']) ?>
</div>

<?= GridView::widget([
    'dataProvider' => $dataProvider,
    'tableOptions' => ['class' => 'table table-striped table-hover align-middle'],
    'columns' => [
        [
            'label' => '',
            'format' => 'raw',
            'value' => static fn (Product $p): string => $p->main_image
                ? Html::img($p->main_image, ['style' => 'width:48px;height:48px;object-fit:cover;border-radius:6px'])
                : '',
        ],
        [
            'attribute' => 'title',
            'format' => 'raw',
            'value' => static fn (Product $p): string => Html::a(Html::encode(mb_strimwidth((string)$p->title, 0, 60, '…')), ['view', 'id' => $p->id]),
        ],
        [
            'attribute' => 'price',
            'value' => static fn (Product $p): string => $p->price !== null ? number_format($p->price / 100, 2) . ' ' . $p->currency_code : '—',
        ],
        'orders_count',
        'review_count',
        [
            'label' => 'Category',
            'value' => static fn (Product $p): string => $p->category->name ?? '—',
        ],
        [
            'label' => 'Store',
            'value' => static fn (Product $p): string => $p->store->name ?? '—',
        ],
        [
            'class' => yii\grid\ActionColumn::class,
            'template' => '{view}',
            'urlCreator' => static fn ($action, Product $p) => ['view', 'id' => $p->id],
        ],
    ],
]) ?>
