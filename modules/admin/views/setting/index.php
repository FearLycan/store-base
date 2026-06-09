<?php

declare(strict_types=1);

use yii\helpers\Html;

/** @var yii\web\View $this */
/** @var string $cookie */
/** @var int|null $updatedAt */

$this->title = 'AliExpress session';
?>
<h1 class="h3 mb-3"><?= Html::encode($this->title) ?></h1>

<div class="row">
    <div class="col-lg-9">
        <div class="alert alert-secondary small">
            Paste the full <code>Cookie</code> header from a logged-in-looking AliExpress browser session
            (at minimum <code>x5sec</code>, plus <code>cna</code>, <code>aep_usuc_f</code>, <code>xman_t</code>).
            In DevTools → Network, find a request to <code>shoprenderview.aliexpress.com</code> →
            <em>Copy as cURL</em>, and paste the value after <code>-H 'Cookie: …'</code> here.
            The store-listing scraper uses it; refresh it when the connection test reports a punish/x5sec error
            (the cookie expires after a few hours).
        </div>

        <?php if ($updatedAt !== null): ?>
            <p class="text-muted small mb-3">Last updated: <?= Yii::$app->formatter->asDatetime($updatedAt) ?></p>
        <?php endif; ?>

        <?= Html::beginForm(['index'], 'post'); ?>
        <div class="mb-3">
            <?= Html::label('Cookie header', 'cookie', ['class' => 'form-label']) ?>
            <?= Html::textarea('cookie', $cookie, [
                'id'    => 'cookie',
                'class' => 'form-control font-monospace',
                'rows'  => 8,
                'placeholder' => 'xman_us_f=…; aep_usuc_f=…; cna=…; x5sec=…',
            ]) ?>
        </div>
        <div class="d-flex gap-2">
            <?= Html::submitButton('Save cookie', ['class' => 'btn btn-primary']) ?>
            <?= Html::a('Test connection', ['test'], ['class' => 'btn btn-outline-secondary']) ?>
        </div>
        <?= Html::endForm(); ?>
    </div>
</div>
