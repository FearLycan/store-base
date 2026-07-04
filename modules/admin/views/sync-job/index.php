<?php

declare(strict_types=1);

use app\models\SyncJob;
use yii\grid\GridView;
use yii\helpers\Html;

/** @var yii\web\View $this */
/** @var yii\data\ActiveDataProvider $dataProvider */
/** @var string|null $status */
/** @var string|null $type */

$this->title = 'Sync queue';
$badge = ['pending' => 'secondary', 'processing' => 'info', 'done' => 'success', 'failed' => 'danger', 'skipped' => 'warning'];
?>
<h1 class="h3 mb-3"><?= Html::encode($this->title) ?></h1>

<div class="mb-3 d-flex gap-2">
    <?php foreach (['' => 'all', 'pending' => 'pending', 'processing' => 'processing', 'done' => 'done', 'failed' => 'failed', 'skipped' => 'skipped'] as $key => $label): ?>
        <?= Html::a($label, ['index', 'status' => $key], [
            'class' => 'btn btn-sm ' . (($status ?? '') === $key ? 'btn-primary' : 'btn-outline-secondary'),
        ]) ?>
    <?php endforeach; ?>
</div>

<?= GridView::widget([
    'dataProvider' => $dataProvider,
    'tableOptions' => ['class' => 'table table-striped table-hover align-middle'],
    'columns' => [
        'id',
        'type',
        [
            'attribute' => 'status',
            'format' => 'raw',
            'value' => static fn (SyncJob $j): string => '<span class="badge text-bg-' . ($badge[$j->status] ?? 'secondary') . '">' . Html::encode($j->status) . '</span>',
        ],
        'store_id',
        'product_id',
        'attempts',
        [
            'attribute' => 'error_message',
            'value' => static fn (SyncJob $j): string => $j->error_message ? mb_strimwidth($j->error_message, 0, 60, '…') : '',
        ],
        [
            'attribute' => 'created_at',
            'value' => static fn (SyncJob $j): string => date('Y-m-d H:i', $j->created_at),
        ],
        [
            'class' => yii\grid\ActionColumn::class,
            'template' => '{retry}',
            'buttons' => [
                'retry' => static function ($url, SyncJob $j): string {
                    if ($j->status !== 'failed') {
                        return '';
                    }

                    return Html::a('Retry', ['retry', 'id' => $j->id], ['class' => 'btn btn-sm btn-outline-primary', 'data-method' => 'post']);
                },
            ],
        ],
    ],
]) ?>
