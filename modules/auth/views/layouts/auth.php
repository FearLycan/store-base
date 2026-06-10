<?php

declare(strict_types=1);

/** @var yii\web\View $this */
/** @var string $content */

use app\assets\AuthAsset;
use app\widgets\Alert;
use yii\helpers\Html;

AuthAsset::register($this);

// Brand-consistent accent: inherit the per-deployment storefront accent.
$accent = Yii::$app->params['site.accentColor'] ?? '#2563eb';
$this->registerCss(":root{--accent:{$accent}}");
?>
<?php $this->beginPage() ?>
<!DOCTYPE html>
<html lang="<?= Yii::$app->language ?>" data-bs-theme="light">
<head>
    <meta charset="<?= Yii::$app->charset ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <?php $this->head() ?>
    <title><?= Html::encode($this->title) ?> · <?= Html::encode(Yii::$app->name) ?></title>
</head>
<body class="auth-page">
<?php $this->beginBody() ?>
<?= $content ?>
<?php $this->endBody() ?>
</body>
</html>
<?php $this->endPage() ?>
