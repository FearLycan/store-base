<?php

declare(strict_types=1);

use yii\helpers\Html;

/** @var yii\web\View $this */
/** @var bool $connected */
/** @var int $expiresAt */
/** @var int|null $updatedAt */
/** @var string $callbackUrl */
/** @var bool $callbackConfigured */

$this->title = 'Dropshipping API';
?>
<h1 class="h3 mb-3"><?= Html::encode($this->title) ?></h1>

<div class="row">
    <div class="col-lg-9">
        <div class="alert alert-secondary small">
            The Dropshipping API supplies the HD image gallery, color/size variants and specification
            attributes the Affiliate API cannot. It is OAuth-gated: authorize once and the
            <code>access_token</code> is stored and auto-refreshed. The importer uses it as the primary
            detail source and falls back to the x5sec scraper only if it fails.
        </div>

        <div class="mb-3">
            <?php if ($connected): ?>
                <span class="badge bg-success">Connected</span>
                <?php if ($expiresAt > 0): ?>
                    <span class="text-muted small ms-2">
                        Token expires: <?= Yii::$app->formatter->asDatetime($expiresAt) ?>
                        <?= $expiresAt <= time() ? ' (expired — will refresh on next use)' : '' ?>
                    </span>
                <?php endif; ?>
                <?php if ($updatedAt !== null): ?>
                    <div class="text-muted small">Last updated: <?= Yii::$app->formatter->asDatetime($updatedAt) ?></div>
                <?php endif; ?>
            <?php else: ?>
                <span class="badge bg-secondary">Not connected</span>
            <?php endif; ?>
        </div>

        <?php if (!$callbackConfigured): ?>
            <div class="alert alert-warning small">
                Before authorizing, set <code>aliexpress.dropshipping.callbackUrl</code> in
                <code>params-local.php</code> and register the <em>same</em> URL as the callback/redirect URI
                in the AliExpress app console. This is a public route (no admin login required). Expected value:
                <br><code><?= Html::encode($callbackUrl) ?></code>
            </div>
        <?php else: ?>
            <p class="text-muted small">Redirect URI in use: <code><?= Html::encode($callbackUrl) ?></code></p>
        <?php endif; ?>

        <div class="d-flex gap-2">
            <?= Html::a($connected ? 'Re-authorize' : 'Connect Dropshipping API', ['ds-authorize'], [
                'class' => 'btn btn-primary',
            ]) ?>
            <?php if ($connected): ?>
                <?= Html::a('Test connection', ['ds-test'], ['class' => 'btn btn-outline-secondary']) ?>
            <?php endif; ?>
        </div>
    </div>
</div>
