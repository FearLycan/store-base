<?php

declare(strict_types=1);

use app\models\SyncJob;
use yii\grid\GridView;
use yii\helpers\Html;
use yii\helpers\Url;

/** @var yii\web\View $this */
/** @var yii\data\ActiveDataProvider $dataProvider */
/** @var string $status */
/** @var string $type */
/** @var array<string,string> $statusOptions */
/** @var array<string,string> $typeOptions */

$this->title = 'Sync queue';
$badge = ['pending' => 'secondary', 'processing' => 'info', 'done' => 'success', 'failed' => 'danger', 'skipped' => 'warning'];
$hasFilter = $status !== '' || $type !== '';
?>
<h1 class="h3 mb-3"><?= Html::encode($this->title) ?></h1>

<form method="get" action="<?= Html::encode(Url::to(['index'])) ?>" class="row g-2 align-items-center mb-3 js-autofilter">
    <div class="col-6 col-sm-auto">
        <?= Html::dropDownList('status', $status, ['' => 'All statuses'] + $statusOptions, [
            'class' => 'form-select',
            'aria-label' => 'Filter by status',
        ]) ?>
    </div>
    <div class="col-6 col-sm-auto">
        <?= Html::dropDownList('type', $type, ['' => 'All types'] + $typeOptions, [
            'class' => 'form-select',
            'aria-label' => 'Filter by type',
        ]) ?>
    </div>
    <div class="col-auto js-autofilter-submit">
        <button type="submit" class="btn btn-primary">Filter</button>
    </div>
    <?php if ($hasFilter): ?>
        <div class="col-auto">
            <?= Html::a('Clear', ['index'], ['class' => 'btn btn-outline-secondary']) ?>
        </div>
    <?php endif; ?>
</form>

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
