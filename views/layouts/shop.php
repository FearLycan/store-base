<?php

/** @var yii\web\View $this */
/** @var string $content */

use app\assets\ShopAsset;
use app\models\Setting;
use yii\helpers\Html;

ShopAsset::register($this);
$accent = (string)(Yii::$app->params['site.accentColor'] ?? '#2563eb');
$customCss = (string)(Setting::get('site.custom_css', '') ?? '');
?>
<?php $this->beginPage() ?>
<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php $this->head() ?>
    <title><?= Html::encode($this->title ?: (Yii::$app->params['site.name'] ?? 'Store')) ?></title>
    <style>:root{--accent: <?= Html::encode($accent) ?>;}</style>
    <?php if (trim($customCss) !== ''): ?><style><?= $customCss /* admin-controlled; raw by design */ ?></style><?php endif; ?>
</head>
<body class="flex min-h-full flex-col bg-gray-50 text-gray-900 antialiased">
<?php $this->beginBody() ?>
    <?= $this->render('shop-header') ?>
    <main class="mx-auto w-full max-w-7xl flex-1 px-4 py-6"><?= $content ?></main>
    <?= $this->render('shop-footer') ?>
    <?= $this->render('_search-modal') ?>
<?php $this->endBody() ?>
</body>
</html>
<?php $this->endPage() ?>
