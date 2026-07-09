<?php

declare(strict_types=1);

use yii\bootstrap5\ActiveForm;
use yii\helpers\Html;

/** @var yii\web\View $this */
/** @var app\modules\admin\models\EditStoreForm $model */
/** @var app\models\Store $store */
/** @var app\models\StoreBanner[] $banners */
/** @var app\modules\admin\models\StoreBannerForm $bannerForm */

$this->title = 'Edit store: ' . $store->name;
?>
<h1 class="h3 mb-3"><?= Html::encode($this->title) ?></h1>

<div class="row">
    <div class="col-lg-6">
        <?php $form = ActiveForm::begin(); ?>
        <?= $form->field($model, 'name')->textInput() ?>
        <?= $form->field($model, 'image_url')->textInput(['placeholder' => 'https://…/logo.png'])->hint('Shown on the storefront store page and product "Sold by" block.') ?>
        <?php if (trim((string) $store->image_url) !== ''): ?>
            <div class="mb-3">
                <img src="<?= Html::encode((string) $store->image_url) ?>" alt="" class="rounded border" style="height:80px;width:80px;object-fit:cover;">
            </div>
        <?php endif; ?>
        <hr>
        <p class="text-muted small mb-2">Social &amp; web links — shown on the public store page. Leave blank to hide.</p>
        <?= $form->field($model, 'website_url')->textInput(['placeholder' => 'https://…']) ?>
        <?= $form->field($model, 'instagram_url')->textInput(['placeholder' => 'https://instagram.com/…']) ?>
        <?= $form->field($model, 'facebook_url')->textInput(['placeholder' => 'https://facebook.com/…']) ?>
        <?= $form->field($model, 'tiktok_url')->textInput(['placeholder' => 'https://tiktok.com/@…']) ?>
        <div class="mt-3">
            <?= Html::submitButton('Save', ['class' => 'btn btn-primary']) ?>
            <?= Html::a('Cancel', ['view', 'id' => $store->id], ['class' => 'btn btn-link']) ?>
        </div>
        <?php ActiveForm::end(); ?>
    </div>

    <div class="col-lg-6">
        <h2 class="h5 mb-2">Hero banners</h2>
        <p class="text-muted small mb-3">Large slider images at the top of the public store page. Shown in sort order; hidden banners stay here but never render.</p>

        <?php if ($banners !== []): ?>
            <table class="table table-sm align-middle mb-4">
                <thead><tr><th></th><th>Headline</th><th>Order</th><th>Status</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($banners as $banner): ?>
                    <tr class="<?= $banner->isActive() ? '' : 'opacity-50' ?>">
                        <td><img src="<?= Html::encode($banner->image_url) ?>" alt="" class="rounded border" style="height:40px;width:94px;object-fit:cover;"></td>
                        <td>
                            <?= Html::encode((string)$banner->headline !== '' ? (string)$banner->headline : '—') ?>
                            <?php if ($banner->link_url): ?><div class="small text-muted text-truncate" style="max-width:180px;"><?= Html::encode($banner->link_url) ?></div><?php endif; ?>
                        </td>
                        <td><?= (int)$banner->sort_order ?></td>
                        <td><span class="badge <?= $banner->isActive() ? 'text-bg-success' : 'text-bg-secondary' ?>"><?= Html::encode($banner->status) ?></span></td>
                        <td class="text-nowrap">
                            <?= Html::beginForm(['banner-toggle', 'id' => $banner->id], 'post', ['class' => 'd-inline']) ?>
                                <?= Html::submitButton($banner->isActive() ? 'Hide' : 'Show', ['class' => 'btn btn-sm btn-outline-secondary']) ?>
                            <?= Html::endForm() ?>
                            <?= Html::beginForm(['banner-delete', 'id' => $banner->id], 'post', ['class' => 'd-inline']) ?>
                                <?= Html::submitButton('Delete', ['class' => 'btn btn-sm btn-outline-danger', 'data-confirm' => 'Delete this banner?']) ?>
                            <?= Html::endForm() ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p class="text-muted small">No banners yet — the store page shows no slider.</p>
        <?php endif; ?>

        <h3 class="h6">Add a banner</h3>
        <?php $bf = ActiveForm::begin(['action' => ['banner-add', 'id' => $store->id], 'options' => ['enctype' => 'multipart/form-data']]); ?>
        <?= $bf->field($bannerForm, 'file')->fileInput(['accept' => 'image/*'])->hint('Recommended: wide image ≥1600×700 (≈21:9). Max 4 MB.') ?>
        <?= $bf->field($bannerForm, 'imageUrl')->textInput(['placeholder' => 'https://…/banner.jpg']) ?>
        <?= $bf->field($bannerForm, 'headline')->textInput(['placeholder' => 'New season picks']) ?>
        <?= $bf->field($bannerForm, 'subheadline')->textInput(['placeholder' => 'Fresh arrivals hand-picked for you']) ?>
        <?= $bf->field($bannerForm, 'linkUrl')->textInput(['placeholder' => '/store/…?sort=newest or https://…']) ?>
        <?= $bf->field($bannerForm, 'sortOrder')->textInput(['type' => 'number', 'style' => 'max-width:8rem']) ?>
        <?= Html::submitButton('Add banner', ['class' => 'btn btn-outline-primary']) ?>
        <?php ActiveForm::end(); ?>
    </div>
</div>
