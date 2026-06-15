<?php

declare(strict_types=1);

/** @var yii\web\View $this */

use yii\helpers\Html;
use yii\helpers\Url;

$icons = [
    'dashboard' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="9" rx="1.5"/><rect x="14" y="3" width="7" height="5" rx="1.5"/><rect x="14" y="12" width="7" height="9" rx="1.5"/><rect x="3" y="16" width="7" height="5" rx="1.5"/></svg>',
    'product' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 8 12 3 3 8l9 5 9-5Z"/><path d="m3 8 0 8 9 5 9-5 0-8"/><path d="M12 13v8"/></svg>',
    'import' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><path d="M7 10l5 5 5-5"/><path d="M12 15V3"/></svg>',
    'store' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9 4.5 4h15L21 9"/><path d="M4 9v10a1 1 0 0 0 1 1h14a1 1 0 0 0 1-1V9"/><path d="M3 9h18"/><path d="M9 20v-6h6v6"/></svg>',
    'sync' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12a9 9 0 0 1-9 9 9 9 0 0 1-7.5-4"/><path d="M3 12a9 9 0 0 1 9-9 9 9 0 0 1 7.5 4"/><path d="M21 3v5h-5"/><path d="M3 21v-5h5"/></svg>',
    'session' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M8.5 8.5h.01M15 9h.01M9 15h.01M14.5 14.5h.01M16 12h.01"/></svg>',
    'appearance' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="13.5" cy="6.5" r="1.5"/><circle cx="17.5" cy="10.5" r="1.5"/><circle cx="6.5" cy="12.5" r="1.5"/><circle cx="8.5" cy="7.5" r="1.5"/><path d="M12 2a10 10 0 1 0 0 20c1.1 0 2-.9 2-2 0-.5-.2-1-.5-1.3-.3-.4-.5-.8-.5-1.2 0-1 .8-1.5 1.8-1.5H17a5 5 0 0 0 5-5c0-5-4.5-8-10-8Z"/></svg>',
    'signout' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="m16 17 5-5-5-5"/><path d="M21 12H9"/></svg>',
];

$ctrl = Yii::$app->controller->id;
$action = Yii::$app->controller->action->id ?? '';

$sections = [
    'Overview' => [
        ['label' => 'Dashboard', 'icon' => 'dashboard', 'url' => ['/admin/dashboard/index'], 'active' => $ctrl === 'dashboard'],
    ],
    'Catalog' => [
        ['label' => 'Products', 'icon' => 'product', 'url' => ['/admin/product/index'], 'active' => $ctrl === 'product' && $action !== 'import'],
        ['label' => 'Import products', 'icon' => 'import', 'url' => ['/admin/product/import'], 'active' => $ctrl === 'product' && $action === 'import'],
    ],
    'Commerce' => [
        ['label' => 'Stores', 'icon' => 'store', 'url' => ['/admin/store/index'], 'active' => $ctrl === 'store'],
        ['label' => 'Sync queue', 'icon' => 'sync', 'url' => ['/admin/sync-job/index'], 'active' => $ctrl === 'sync-job'],
    ],
    'Settings' => [
        ['label' => 'Session', 'icon' => 'session', 'url' => ['/admin/setting/index'], 'active' => $ctrl === 'setting' && in_array($action, ['index', 'test'], true)],
        ['label' => 'Dropshipping API', 'icon' => 'sync', 'url' => ['/admin/setting/ds'], 'active' => $ctrl === 'setting' && str_starts_with($action, 'ds')],
        ['label' => 'Appearance', 'icon' => 'appearance', 'url' => ['/admin/setting/appearance'], 'active' => $ctrl === 'setting' && $action === 'appearance'],
    ],
];

/** @var \app\models\User|null $identity */
$identity = Yii::$app->user->identity;
$username = $identity?->username ?? 'Admin';
$initial = strtoupper(mb_substr($username, 0, 1));
?>
<aside class="admin-sidebar" id="admin-sidebar">
    <?= Html::a(
        '<span class="admin-brand__diamond" aria-hidden="true"></span>' . Html::encode(Yii::$app->name),
        ['/admin/dashboard/index'],
        ['class' => 'admin-brand'],
    ) ?>

    <nav class="admin-nav">
        <?php foreach ($sections as $label => $links): ?>
            <div class="admin-nav__label"><?= Html::encode($label) ?></div>
            <?php foreach ($links as $link): ?>
                <?= Html::a(
                    $icons[$link['icon']]
                    . '<span>' . Html::encode($link['label']) . '</span>',
                    $link['url'],
                    ['class' => 'admin-nav__link' . ($link['active'] ? ' is-active' : '')],
                ) ?>
            <?php endforeach ?>
        <?php endforeach ?>
    </nav>

    <div class="admin-user">
        <div class="admin-user__row">
            <div class="admin-user__avatar" aria-hidden="true"><?= Html::encode($initial) ?></div>
            <div>
                <div class="admin-user__name"><?= Html::encode($username) ?></div>
                <div class="admin-user__role">Administrator</div>
            </div>
        </div>
        <?= Html::beginForm(Url::to(['/auth/default/logout']), 'post') ?>
        <button type="submit" class="admin-signout">
            <?= $icons['signout'] ?><span>Sign out</span>
        </button>
        <?= Html::endForm() ?>
    </div>
</aside>
