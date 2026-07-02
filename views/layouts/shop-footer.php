<?php

/** @var yii\web\View $this */

use yii\helpers\Html;

$params = Yii::$app->params;
$footer = (string)($params['site.footer'] ?? '');
$name = (string)($params['site.name'] ?? 'Store');
$brand = (string)($params['brand.name'] ?? 'snagloft');
$hubUrl = (string)($params['brand.hubUrl'] ?? 'https://snagloft.com');
$slogan = (string)($params['brand.slogan'] ?? '');
$hubHost = preg_replace('~^https?://~', '', rtrim($hubUrl, '/'));
?>
<footer class="mt-12 border-t border-gray-200 bg-white">
    <div class="mx-auto max-w-7xl px-4 py-10">
        <div class="flex flex-col justify-between gap-8 md:flex-row md:items-end">
            <div>
                <a href="<?= Html::encode($hubUrl) ?>" class="inline-flex items-center gap-1.5" aria-label="<?= Html::encode($brand) ?> home">
                    <?= $this->render('_brand-logo', ['iconSize' => 36, 'wordmarkHeight' => 36]) ?>
                </a>
                <?php if ($slogan !== ''): ?>
                    <p class="mt-3 max-w-sm text-sm leading-relaxed text-gray-500"><?= Html::encode($slogan) ?></p>
                <?php endif; ?>
                <p class="mt-2 max-w-sm text-sm leading-relaxed text-gray-500">
                    <?= Html::encode($name) ?> is one shelf in the <?= Html::encode($brand) ?> loft —
                    browse the other shelves at <a href="<?= Html::encode($hubUrl) ?>" class="font-medium text-[color:var(--accent)] hover:underline"><?= Html::encode($hubHost) ?></a>.
                </p>
            </div>
            <nav class="flex flex-wrap gap-x-6 gap-y-2 text-[11px] font-semibold uppercase tracking-[0.14em] text-gray-500">
                <a href="<?= Html::encode($hubUrl) ?>" class="transition-colors hover:text-[color:var(--accent)]"><?= Html::encode($hubHost) ?></a>
            </nav>
        </div>
        <div class="mt-8 flex flex-col gap-2 border-t border-gray-100 pt-5 text-xs text-gray-400 sm:flex-row sm:justify-between">
            <p><?= Html::encode($footer !== '' ? $footer : ('© ' . date('Y') . ' ' . $brand . '. Trademarks belong to their respective owners.')) ?></p>
            <p>Product links are affiliate links. We may earn a small commission, but it never changes your price.</p>
        </div>
    </div>
</footer>
