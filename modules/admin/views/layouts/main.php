<?php

declare(strict_types=1);

/** @var yii\web\View $this */
/** @var string $content */

use app\assets\AdminAsset;
use app\widgets\Alert;
use yii\helpers\Html;

AdminAsset::register($this);

// Brand-consistent accent: inherit the per-deployment storefront accent.
$accent = Yii::$app->params['site.accentColor'] ?? '#2563eb';
$this->registerCss(":root{--accent:{$accent}}");

// Lightweight off-canvas toggle for the sidebar on small screens.
$this->registerJs(<<<'JS'
(function () {
    var shell = document.getElementById('admin-shell');
    if (!shell) return;
    function toggle(open) { shell.classList[open ? 'add' : 'remove']('is-nav-open'); }
    var burger = document.getElementById('admin-burger');
    var scrim = document.getElementById('admin-scrim');
    if (burger) burger.addEventListener('click', function () { toggle(!shell.classList.contains('is-nav-open')); });
    if (scrim) scrim.addEventListener('click', function () { toggle(false); });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') toggle(false); });
})();
JS);

// Auto-submitting list filters: any GET form tagged `.js-autofilter` re-runs the
// moment a select changes (or a search box loses focus / Enter is pressed), so the
// list reflows without an explicit "Filter" click. Selects submit instantly; the
// redundant submit button is hidden when JS is available.
$this->registerJs(<<<'JS'
(function () {
    function submit(form) { form.requestSubmit ? form.requestSubmit() : form.submit(); }
    document.querySelectorAll('form.js-autofilter').forEach(function (form) {
        form.querySelectorAll('select').forEach(function (sel) {
            sel.addEventListener('change', function () { submit(form); });
        });
        var search = form.querySelector('input[type=search]');
        if (search) {
            search.addEventListener('change', function () { submit(form); });
        }
        form.querySelectorAll('.js-autofilter-submit').forEach(function (btn) { btn.hidden = true; });
    });
})();
JS);

$burger = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M4 6h16M4 12h16M4 18h16"/></svg>';
$external = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 3h6v6"/><path d="M10 14 21 3"/><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/></svg>';
?>
<?php $this->beginPage() ?>
<!DOCTYPE html>
<html lang="<?= Yii::$app->language ?>" data-bs-theme="light">
<head>
    <meta charset="<?= Yii::$app->charset ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <?= Html::csrfMetaTags() ?>
    <?php $this->head() ?>
    <title><?= Html::encode($this->title) ?> · <?= Html::encode(Yii::$app->name) ?> admin</title>
</head>
<body class="admin-body">
<?php $this->beginBody() ?>

<div class="admin-shell" id="admin-shell">
    <?= $this->render('_sidebar') ?>
    <div class="admin-scrim" id="admin-scrim" aria-hidden="true"></div>

    <div class="admin-main">
        <header class="admin-topbar">
            <button type="button" class="admin-burger" id="admin-burger" aria-label="Toggle navigation"><?= $burger ?></button>
            <span class="admin-topbar__crumb"><?= Html::encode($this->title) ?></span>
            <span class="admin-topbar__spacer"></span>
            <?= Html::a($external . '<span>View storefront</span>', ['/catalog/index'], [
                'class' => 'admin-topbar__link',
                'target' => '_blank',
                'rel' => 'noopener',
            ]) ?>
        </header>

        <main class="admin-content">
            <?= Alert::widget() ?>
            <?= $content ?>
        </main>
    </div>
</div>

<?php $this->endBody() ?>
</body>
</html>
<?php $this->endPage() ?>
