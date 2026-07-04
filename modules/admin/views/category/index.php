<?php

declare(strict_types=1);

use app\enums\CategoryStatusEnum;
use app\models\Category;
use yii\grid\GridView;
use yii\helpers\Html;
use yii\helpers\Url;

/** @var yii\web\View $this */
/** @var yii\data\ActiveDataProvider $dataProvider */
/** @var string $q */
/** @var string $status */

$this->title = 'Categories';

$statusOptions = [];
foreach (CategoryStatusEnum::cases() as $case) {
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
JS
);
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h3 mb-0"><?= Html::encode($this->title) ?></h1>
</div>

<p class="text-secondary mb-3">
    Top-level categories appear on the storefront home as cover tiles. Set a custom image below; when none is set,
    the tile shows the category's best-selling product photo automatically.
</p>

<?= $this->render('/_partials/_filter', [
    'action'      => 'index',
    'q'           => $q,
    'status'      => $status,
    'statuses'    => $statusOptions,
    'placeholder' => 'Search categories by name…',
]) ?>

<?= GridView::widget([
    'dataProvider' => $dataProvider,
    'tableOptions' => ['class' => 'table table-striped table-hover align-middle'],
    'columns'      => [
        [
            'attribute'      => 'image_url',
            'label'          => 'Image',
            'format'         => 'raw',
            'contentOptions' => ['style' => 'width:84px'],
            'value'          => static function (Category $c): string {
                if ((string) $c->image_url === '') {
                    return '<span class="text-secondary small">auto</span>';
                }
                return Html::img($c->image_url, [
                    'style' => 'width:64px;height:64px;object-fit:cover;border-radius:8px;',
                    'alt'   => '',
                ]);
            },
        ],
        [
            'attribute' => 'name',
            'format'    => 'raw',
            'value'     => static fn(Category $c): string => Html::a(Html::encode($c->name), ['update', 'id' => $c->id]),
        ],
        [
            'attribute' => 'level',
            'label'  => 'Level',
            'format' => 'raw',
            'value'  => static fn(Category $c): string => $c->level === 1
                ? '<span class="badge text-bg-primary">top-level</span>'
                : '<span class="badge text-bg-secondary">sub</span>',
        ],
        'slug',
        [
            'attribute' => 'image_url',
            'label'  => 'Cover',
            'format' => 'raw',
            'value'  => static fn(Category $c): string => (string) $c->image_url !== ''
                ? '<span class="badge text-bg-success">custom</span>'
                : '<span class="badge text-bg-light text-secondary">best product</span>',
        ],
        [
            'attribute' => 'status',
            'label'  => 'Status',
            'format' => 'raw',
            'value'  => static function (Category $c): string {
                $status = CategoryStatusEnum::tryFrom($c->status) ?? CategoryStatusEnum::INACTIVE;
                $url = Url::to(['toggle-status', 'id' => $c->id]);

                return Html::tag('div', Html::tag('input', '', [
                        'class'      => 'form-check-input js-status-switch',
                        'type'       => 'checkbox',
                        'role'       => 'switch',
                        'checked'    => $status === CategoryStatusEnum::ACTIVE,
                        'aria-label' => 'Toggle category status',
                    ]) . Html::tag('span', Html::encode($status->label()), [
                        'class' => 'js-status-label badge ' . $status->badgeClass(),
                    ]), [
                    'class'    => 'form-check form-switch js-status-toggle d-flex align-items-center gap-2',
                    'data-url' => $url,
                ]);
            },
        ],
        [
            'class'      => yii\grid\ActionColumn::class,
            'template'   => '{update}',
            'buttons'    => [
                'update' => static fn($url): string => Html::a('Edit', $url, ['class' => 'btn btn-sm btn-outline-primary']),
            ],
            'urlCreator' => static fn($action, Category $c) => ['update', 'id' => $c->id],
        ],
    ],
]) ?>
