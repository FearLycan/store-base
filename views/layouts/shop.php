<?php

/** @var yii\web\View $this */
/** @var string $content */

use app\assets\ShopAsset;
use app\models\Setting;
use yii\helpers\Html;

ShopAsset::register($this);
$params = Yii::$app->params;
// Store accent: explicit override, else the hub palette entry for this niche.
$accent = (string)($params['site.accentColor'] ?? '');
if ($accent === '') {
    $palette = (array)($params['brand.palette'] ?? []);
    $accent = (string)($palette[(string)($params['site.niche'] ?? '')] ?? ($params['brand.defaultAccent'] ?? '#E0592E'));
}
$ink = (string)($params['brand.ink'] ?? '#1F1E1C');
$cream = (string)($params['brand.cream'] ?? '#F4EFE6');
$customCss = (string)(Setting::get('site.custom_css', '') ?? '');

// snagloft icon as an accent-tinted SVG favicon (matches the hub brand pack).
$faviconSvg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100">'
    . '<rect width="100" height="100" rx="22" fill="' . $ink . '"/>'
    . '<path d="M28 50 L50 18 L72 50" fill="none" stroke="' . $cream . '" stroke-width="10" stroke-linecap="round" stroke-linejoin="round"/>'
    . '<path d="M50 50 L50 72 C50 84 64 86 72 77" fill="none" stroke="' . $accent . '" stroke-width="10" stroke-linecap="round"/>'
    . '</svg>';
?>
<?php $this->beginPage() ?>
<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php $this->head() ?>
    <title><?= Html::encode($this->title ?: ($params['site.name'] ?? 'Store')) ?></title>
    <meta name="theme-color" content="#f9fafb">
    <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,<?= rawurlencode($faviconSvg) ?>">
    <style>:root{--accent: <?= Html::encode($accent) ?>;--brand-ink: <?= Html::encode($ink) ?>;--brand-cream: <?= Html::encode($cream) ?>;}</style>
    <?php if (trim($customCss) !== ''): ?><style><?= $customCss /* admin-controlled; raw by design */ ?></style><?php endif; ?>
</head>
<body class="flex min-h-full flex-col bg-gray-50 text-gray-900 antialiased">
<?php $this->beginBody() ?>
    <?= $this->render('shop-header') ?>
    <main class="mx-auto w-full max-w-7xl flex-1 px-4 py-6"><?= $content ?></main>
    <?= $this->render('shop-footer') ?>
    <?= $this->render('_search-modal') ?>
    <?= $this->render('_back-to-top') ?>
<?php $this->endBody() ?>
</body>
</html>
<?php $this->endPage() ?>
