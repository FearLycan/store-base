<?php

declare(strict_types=1);

use app\models\Category;
use yii\grid\GridView;
use yii\helpers\Html;

/** @var yii\web\View $this */
/** @var yii\data\ActiveDataProvider $dataProvider */

$this->title = 'Categories';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h3 mb-0"><?= Html::encode($this->title) ?></h1>
</div>

<p class="text-secondary mb-3">
    Top-level categories appear on the storefront home as cover tiles. Set a custom image below; when none is set,
    the tile shows the category's best-selling product photo automatically.
</p>

<?= GridView::widget([
    'dataProvider' => $dataProvider,
    'tableOptions' => ['class' => 'table table-striped table-hover align-middle'],
    'columns' => [
        [
            'header' => 'Image',
            'format' => 'raw',
            'contentOptions' => ['style' => 'width:84px'],
            'value' => static function (Category $c): string {
                if ((string)$c->image_url === '') {
                    return '<span class="text-secondary small">auto</span>';
                }
                return Html::img($c->image_url, [
                    'style' => 'width:64px;height:64px;object-fit:cover;border-radius:8px;',
                    'alt' => '',
                ]);
            },
        ],
        [
            'attribute' => 'name',
            'format' => 'raw',
            'value' => static fn (Category $c): string => Html::a(Html::encode($c->name), ['update', 'id' => $c->id]),
        ],
        [
            'header' => 'Level',
            'format' => 'raw',
            'value' => static fn (Category $c): string => $c->level === 1
                ? '<span class="badge text-bg-primary">top-level</span>'
                : '<span class="badge text-bg-secondary">sub</span>',
        ],
        'slug',
        [
            'header' => 'Cover',
            'format' => 'raw',
            'value' => static fn (Category $c): string => (string)$c->image_url !== ''
                ? '<span class="badge text-bg-success">custom</span>'
                : '<span class="badge text-bg-light text-secondary">best product</span>',
        ],
        [
            'class' => yii\grid\ActionColumn::class,
            'template' => '{update}',
            'buttons' => [
                'update' => static fn ($url): string => Html::a('Edit image', $url, ['class' => 'btn btn-sm btn-outline-primary']),
            ],
            'urlCreator' => static fn ($action, Category $c) => ['update', 'id' => $c->id],
        ],
    ],
]) ?>
