<?php

declare(strict_types=1);

use yii\helpers\Html;
use yii\widgets\DetailView;

/** @var yii\web\View $this */
/** @var app\models\Product $product */

$this->title = (string)$product->title;
$price = $product->price !== null ? number_format($product->price / 100, 2) . ' ' . $product->currency_code : '—';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 mb-0"><?= Html::encode(mb_strimwidth($this->title, 0, 80, '…')) ?></h1>
    <div>
        <?= Html::a('Refresh', ['refresh', 'id' => $product->id], ['class' => 'btn btn-outline-primary', 'data-method' => 'post']) ?>
        <?php if ($product->affiliate_url): ?>
            <?= Html::a('Affiliate link ↗', $product->affiliate_url, ['class' => 'btn btn-outline-success', 'target' => '_blank']) ?>
        <?php endif; ?>
    </div>
</div>

<div class="row">
    <div class="col-md-5">
        <?php if ($product->images): ?>
            <div class="d-flex flex-wrap gap-2">
                <?php foreach ($product->images as $img): ?>
                    <?= Html::img($img->url, ['style' => 'width:96px;height:96px;object-fit:cover;border-radius:8px']) ?>
                <?php endforeach; ?>
            </div>
        <?php elseif ($product->main_image): ?>
            <?= Html::img($product->main_image, ['style' => 'max-width:100%;border-radius:8px']) ?>
        <?php endif; ?>
    </div>
    <div class="col-md-7">
        <?= DetailView::widget([
            'model' => $product,
            'options' => ['class' => 'table table-bordered detail-view'],
            'attributes' => [
                'id',
                'external_id',
                ['label' => 'Price', 'value' => $price],
                ['label' => 'Category', 'value' => $product->category->name ?? '—'],
                ['label' => 'Rating', 'value' => $product->rating_value !== null ? $product->rating_value . ' / ' . ($product->rating_scale_max ?? '5') : '—'],
                'review_count',
                'orders_count',
                'availability',
                'status',
            ],
        ]) ?>
    </div>
</div>

<?php if ($product->specs): ?>
    <h2 class="h5 mt-4">Specifications</h2>
    <table class="table table-sm w-auto">
        <?php foreach ($product->specs as $spec): ?>
            <tr><th class="text-muted pe-3"><?= Html::encode($spec->name) ?></th><td><?= Html::encode((string)$spec->value) ?></td></tr>
        <?php endforeach; ?>
    </table>
<?php endif; ?>

<?php if ($product->variants): ?>
    <h2 class="h5 mt-4">Variants</h2>
    <table class="table table-sm">
        <thead><tr><th>SKU</th><th>Name</th><th>Price</th><th>Stock</th></tr></thead>
        <tbody>
        <?php foreach ($product->variants as $v): ?>
            <tr>
                <td><?= Html::encode((string)$v->external_sku_id) ?></td>
                <td><?= Html::encode((string)$v->name) ?></td>
                <td><?= $v->price !== null ? number_format($v->price / 100, 2) . ' ' . (string)$v->currency_code : '—' ?></td>
                <td><?= $v->stock ?? '—' ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>

<h2 class="h5 mt-4">Reviews (<?= count($product->reviews) ?>)</h2>
<?php foreach ($product->reviews as $r): ?>
    <div class="border rounded p-2 mb-2">
        <div class="small text-muted">
            <?= Html::encode((string)($r->author_name ?? 'Anonymous')) ?>
            <?php if ($r->reviewer_country): ?> · <?= Html::encode($r->reviewer_country) ?><?php endif; ?>
            <?php if ($r->rating_value !== null): ?> · <?= Html::encode($r->rating_value) ?>/<?= Html::encode((string)($r->rating_scale_max ?? '5')) ?>★<?php endif; ?>
            <?php if ($r->reviewed_at): ?> · <?= date('Y-m-d', $r->reviewed_at) ?><?php endif; ?>
        </div>
        <?php if ($r->content): ?><div><?= Html::encode($r->content) ?></div><?php endif; ?>
        <?php if ($r->images): ?>
            <div class="d-flex flex-wrap gap-1 mt-1">
                <?php foreach ($r->images as $ri): ?>
                    <?= Html::img($ri->url, ['style' => 'width:64px;height:64px;object-fit:cover;border-radius:6px']) ?>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
<?php endforeach; ?>
