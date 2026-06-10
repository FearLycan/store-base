<?php

declare(strict_types=1);

/** @var yii\web\View $this */
/** @var string $title */
/** @var string $text */
/** @var string[] $points */

use yii\helpers\Html;

$check = <<<SVG
<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>
SVG;
?>
<aside class="auth-brand">
    <div class="auth-brand__mark">
        <span class="auth-brand__diamond" aria-hidden="true"></span>
        <?= Html::encode(Yii::$app->name) ?>
    </div>

    <div class="auth-brand__body">
        <h2 class="auth-brand__title"><?= $title ?></h2>
        <p class="auth-brand__text"><?= Html::encode($text) ?></p>
    </div>

    <?php if ($points !== []): ?>
        <ul class="auth-brand__points">
            <?php foreach ($points as $point): ?>
                <li><?= $check ?><span><?= Html::encode($point) ?></span></li>
            <?php endforeach ?>
        </ul>
    <?php endif ?>
</aside>
