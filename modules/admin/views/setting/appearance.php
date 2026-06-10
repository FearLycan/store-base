<?php

declare(strict_types=1);

use yii\helpers\Html;

/** @var yii\web\View $this */
/** @var string $customCss */
/** @var int|null $updatedAt */

$this->title = 'Appearance (Custom CSS)';
?>
<h1 class="h3 mb-3"><?= Html::encode($this->title) ?></h1>
<div class="row">
    <div class="col-lg-9">
        <div class="alert alert-secondary small">CSS entered here is injected into the public shop <code>&lt;head&gt;</code> after Tailwind, so it overrides the theme. Per-deployment tweaks without touching files.</div>
        <?php if ($updatedAt !== null): ?><p class="text-muted small mb-3">Last updated: <?= Yii::$app->formatter->asDatetime($updatedAt) ?></p><?php endif; ?>
        <?= Html::beginForm(['appearance'], 'post'); ?>
        <div class="mb-3">
            <?= Html::label('Custom CSS', 'custom_css', ['class' => 'form-label']) ?>
            <?= Html::textarea('custom_css', $customCss, ['id' => 'custom_css', 'class' => 'form-control font-monospace', 'rows' => 14, 'placeholder' => ":root{ --accent:#b91c1c; }"]) ?>
        </div>
        <?= Html::submitButton('Save CSS', ['class' => 'btn btn-primary']) ?>
        <?= Html::endForm(); ?>
    </div>
</div>
