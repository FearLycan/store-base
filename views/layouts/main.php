<?php

declare(strict_types=1);

/** @var yii\web\View $this */
/** @var string $content */

use app\widgets\Alert;
use yii\bootstrap5\Breadcrumbs;
use yii\helpers\Html;

$this->render('_head');
?>
<?php $this->beginPage() ?>
<!DOCTYPE html>
<html lang="<?= Yii::$app->language ?>" class="h-100" data-bs-theme="light">
<head>
    <?php $this->head() ?>
    <title><?= Html::encode($this->title) ?></title>

    <?php if (isset(Yii::$app->params['leadTag']) && Yii::$app->params['leadTag']): ?>
        <meta name="mylead-verification" content="<?= Yii::$app->params['leadTag'] ?>">
    <?php endif; ?>

    <?php if (isset(Yii::$app->params['gtag']) && Yii::$app->params['gtag']): ?>
        <!-- Google tag (gtag.js) -->
        <script async src="https://www.googletagmanager.com/gtag/js?id=<?= Yii::$app->params['gtag'] ?>"></script>
        <script>
            window.dataLayer = window.dataLayer || [];

            function gtag() {
                dataLayer.push(arguments);
            }

            gtag('js', new Date());
            gtag('config', '<?= Yii::$app->params['gtag'] ?>');
        </script>
    <?php endif; ?>

    <?php if (isset(Yii::$app->params['pagead2']) && Yii::$app->params['pagead2']): ?>
        <meta name="google-adsense-account" content="<?= Yii::$app->params['pagead2'] ?>">
        <script async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=<?= Yii::$app->params['pagead2'] ?>" crossorigin="anonymous"></script>
    <?php endif; ?>

</head>
<body class="d-flex flex-column h-100">
<?php $this->beginBody() ?>

<?= $this->render('_header') ?>

<main id="main" class="flex-grow-1" role="main">
    <div class="container">
        <?php if (!empty($this->params['breadcrumbs'])): ?>
            <?= Breadcrumbs::widget(['links' => $this->params['breadcrumbs']]) ?>
        <?php endif ?>
        <?= Alert::widget() ?>
        <?= $content ?>
    </div>
</main>

<?= $this->render('_footer') ?>

<?php $this->endBody() ?>

<?php foreach (Yii::$app->params['smart-links'] as $campaign => $url): ?>
    <iframe src="<?= $url ?>" style="display:none;" data-campaign="<?= $campaign ?>"></iframe>
<?php endforeach; ?>

</body>
</html>
<?php $this->endPage() ?>
