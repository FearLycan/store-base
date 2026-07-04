<?php

declare(strict_types=1);

use app\enums\ProductStatusEnum;
use app\models\Product;
use yii\grid\GridView;
use yii\helpers\Html;
use yii\helpers\Url;

/** @var yii\web\View $this */
/** @var yii\data\ActiveDataProvider $dataProvider */
/** @var int|null $storeId */
/** @var array<int,string> $stores */
/** @var string $q */
/** @var string $status */

$this->title = 'Products';

$statusOptions = [];
foreach (ProductStatusEnum::cases() as $case) {
    $statusOptions[$case->value] = $case->label();
}

$this->registerJs(<<<'JS'
document.addEventListener('change', function (e) {
    var input = e.target;
    if (!input.classList || !input.classList.contains('js-status-switch')) return;

    var wrap = input.closest('.js-status-toggle');
    var label = wrap.querySelector('.js-status-label');
    var headers = {'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json'};
    var token = document.querySelector('meta[name=csrf-token]');
    if (token) headers['X-CSRF-Token'] = token.getAttribute('content');

    input.disabled = true;
    fetch(wrap.getAttribute('data-url'), {method: 'POST', headers: headers, credentials: 'same-origin'})
        .then(function (r) { if (!r.ok) throw new Error(r.status); return r.json(); })
        .then(function (data) {
            input.checked = data.active;
            if (label) {
                label.textContent = data.label;
                label.className = 'js-status-label badge ' + data.badgeClass;
            }
        })
        .catch(function () { input.checked = !input.checked; })
        .finally(function () { input.disabled = false; });
});
JS);
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h3 mb-0"><?= Html::encode($this->title) ?></h1>
    <?= Html::a('Import products', ['import'], ['class' => 'btn btn-primary']) ?>
</div>

<?= $this->render('/_partials/_filter', [
    'action' => 'index',
    'q' => $q,
    'status' => $status,
    'statuses' => $statusOptions,
    'stores' => $stores,
    'storeId' => $storeId,
    'placeholder' => 'Search products by title…',
]) ?>

<?= GridView::widget([
    'dataProvider' => $dataProvider,
    'tableOptions' => ['class' => 'table table-striped table-hover align-middle'],
    'columns' => [
        [
            'attribute' => 'main_image',
            'label' => 'Image',
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
            'attribute' => 'category',
            'label' => 'Category',
            'value' => static fn (Product $p): string => $p->category->name ?? '—',
        ],
        [
            'attribute' => 'store',
            'label' => 'Store',
            'value' => static fn (Product $p): string => $p->store->name ?? '—',
        ],
        [
            'attribute' => 'status',
            'label' => 'Status',
            'format' => 'raw',
            'value' => static function (Product $p): string {
                $status = ProductStatusEnum::tryFrom($p->status) ?? ProductStatusEnum::INACTIVE;
                $isActive = $status === ProductStatusEnum::ACTIVE;
                $url = Url::to(['toggle-status', 'id' => $p->id]);

                return Html::tag('div', Html::tag('input', '', [
                        'class' => 'form-check-input js-status-switch',
                        'type' => 'checkbox',
                        'role' => 'switch',
                        'checked' => $isActive,
                        'aria-label' => 'Toggle product status',
                    ]) . Html::tag('span', Html::encode($status->label()), [
                        'class' => 'js-status-label badge ' . $status->badgeClass(),
                    ]), [
                        'class' => 'form-check form-switch js-status-toggle d-flex align-items-center gap-2',
                        'data-url' => $url,
                    ]);
            },
        ],
        [
            'attribute' => 'created_at',
            'label' => 'Created',
            'value' => static fn (Product $p): string => date('Y-m-d H:i', $p->created_at),
        ],
        /*[
            'class' => yii\grid\ActionColumn::class,
            'template' => '{view}',
            'urlCreator' => static fn ($action, Product $p) => ['view', 'id' => $p->id],
        ],*/
    ],
]) ?>
