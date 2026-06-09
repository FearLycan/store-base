<?php

declare(strict_types=1);

use app\models\Store;
use yii\grid\GridView;
use yii\helpers\Html;

/** @var yii\web\View $this */
/** @var yii\data\ActiveDataProvider $dataProvider */

$this->title = 'Stores';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h3 mb-0"><?= Html::encode($this->title) ?></h1>
    <div>
        <?= Html::a('Import products', ['/admin/product/import'], ['class' => 'btn btn-outline-secondary']) ?>
        <?= Html::a('+ Add store', ['add'], ['class' => 'btn btn-primary']) ?>
    </div>
</div>

<?= GridView::widget([
    'dataProvider' => $dataProvider,
    'tableOptions' => ['class' => 'table table-striped table-hover align-middle'],
    'columns' => [
        'id',
        [
            'attribute' => 'name',
            'format' => 'raw',
            'value' => static fn (Store $s): string => Html::a(Html::encode($s->name), ['view', 'id' => $s->id]),
        ],
        'external_store_id',
        [
            'attribute' => 'status',
            'format' => 'raw',
            'value' => static fn (Store $s): string => $s->isActive()
                ? '<span class="badge text-bg-success">active</span>'
                : '<span class="badge text-bg-secondary">paused</span>',
        ],
        'product_count',
        [
            'attribute' => 'last_discovery_at',
            'value' => static fn (Store $s): string => $s->last_discovery_at ? date('Y-m-d H:i', $s->last_discovery_at) : '—',
        ],
        [
            'class' => yii\grid\ActionColumn::class,
            'template' => '{view}',
            'urlCreator' => static fn ($action, Store $s) => ['view', 'id' => $s->id],
        ],
    ],
]) ?>
