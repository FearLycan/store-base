<?php

declare(strict_types=1);

use app\models\Product;
use yii\grid\GridView;
use yii\helpers\Html;
use yii\widgets\DetailView;

/** @var yii\web\View $this */
/** @var app\models\Store $store */
/** @var yii\data\ActiveDataProvider $products */

$this->title = $store->name;
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h3 mb-0"><?= Html::encode($store->name) ?></h1>
    <div>
        <?= Html::a('Edit', ['edit', 'id' => $store->id], ['class' => 'btn btn-outline-secondary']) ?>
        <?= Html::a('Import products', ['/admin/product/import'], ['class' => 'btn btn-outline-secondary']) ?>
        <?= Html::a('Sync now', ['sync-now', 'id' => $store->id], ['class' => 'btn btn-outline-primary', 'data-method' => 'post']) ?>
        <?php if ($store->isActive()): ?>
            <?= Html::a('Pause', ['pause', 'id' => $store->id], ['class' => 'btn btn-outline-warning', 'data-method' => 'post']) ?>
        <?php else: ?>
            <?= Html::a('Resume', ['resume', 'id' => $store->id], ['class' => 'btn btn-outline-success', 'data-method' => 'post']) ?>
        <?php endif; ?>
    </div>
</div>

<?= DetailView::widget([
    'model' => $store,
    'options' => ['class' => 'table table-bordered detail-view w-auto'],
    'attributes' => [
        'id',
        'external_store_id',
        'slug',
        [
            'attribute' => 'image_url',
            'format' => 'raw',
            'value' => $store->image_url
                ? Html::img($store->image_url, ['style' => 'height:64px;width:64px;object-fit:cover;', 'class' => 'rounded border'])
                : '—',
        ],
        ['attribute' => 'url', 'format' => 'url'],
        ['attribute' => 'website_url', 'format' => 'url', 'value' => $store->website_url ?: null],
        ['attribute' => 'instagram_url', 'format' => 'url', 'value' => $store->instagram_url ?: null],
        ['attribute' => 'facebook_url', 'format' => 'url', 'value' => $store->facebook_url ?: null],
        ['attribute' => 'tiktok_url', 'format' => 'url', 'value' => $store->tiktok_url ?: null],
        'status',
        'seller_admin_seq',
        'product_count',
        ['attribute' => 'last_discovery_at', 'value' => $store->last_discovery_at ? date('Y-m-d H:i', $store->last_discovery_at) : '—'],
    ],
]) ?>

<h2 class="h5 mt-4">Products (<?= $products->getTotalCount() ?>)</h2>
<?= GridView::widget([
    'dataProvider' => $products,
    'tableOptions' => ['class' => 'table table-striped align-middle'],
    'columns' => [
        'id',
        [
            'attribute' => 'title',
            'format' => 'raw',
            'value' => static fn (Product $p): string => Html::a(Html::encode((string)$p->title), ['/admin/product/view', 'id' => $p->id]),
        ],
        [
            'attribute' => 'price',
            'value' => static fn (Product $p): string => $p->price !== null ? number_format($p->price / 100, 2) . ' ' . $p->currency_code : '—',
        ],
        'orders_count',
        'review_count',
    ],
]) ?>
