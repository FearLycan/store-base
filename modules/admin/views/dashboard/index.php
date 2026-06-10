<?php

declare(strict_types=1);

/** @var yii\web\View $this */
/** @var array<int, array{label:string,value:int,icon:string,url?:array,badge?:?string}> $cards */

use yii\helpers\Html;
use yii\helpers\Url;

$this->title = 'Dashboard';

$icons = [
    'product' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 8 12 3 3 8l9 5 9-5Z"/><path d="m3 8 0 8 9 5 9-5 0-8"/><path d="M12 13v8"/></svg>',
    'store' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9 4.5 4h15L21 9"/><path d="M4 9v10a1 1 0 0 0 1 1h14a1 1 0 0 0 1-1V9"/><path d="M3 9h18"/><path d="M9 20v-6h6v6"/></svg>',
    'category' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20.6 13.4 13.4 20.6a2 2 0 0 1-2.8 0l-7-7a2 2 0 0 1-.6-1.4V5a2 2 0 0 1 2-2h6.2a2 2 0 0 1 1.4.6l7 7a2 2 0 0 1 0 2.8Z"/><circle cx="7.5" cy="7.5" r="1.4"/></svg>',
    'variant' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/></svg>',
    'review' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m12 3 2.7 5.5 6 .9-4.3 4.2 1 6L12 17l-5.4 2.6 1-6L3.3 9.4l6-.9Z"/></svg>',
    'click' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 9 4 4l1.5 6.5L9 9Z" transform="translate(2 2)"/><path d="m9 9 12 5-5 2-2 5-5-12Z"/></svg>',
    'sync' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12a9 9 0 0 1-9 9 9 9 0 0 1-7.5-4"/><path d="M3 12a9 9 0 0 1 9-9 9 9 0 0 1 7.5 4"/><path d="M21 3v5h-5"/><path d="M3 21v-5h5"/></svg>',
    'users' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13A4 4 0 0 1 16 11"/></svg>',
];
?>
<div class="admin-page-head">
    <h1>Dashboard</h1>
    <p>An overview of everything in your catalogue right now.</p>
</div>

<div class="dash-grid">
    <?php foreach ($cards as $card): ?>
        <?php
        $icon = '<div class="stat-card__icon">' . ($icons[$card['icon']] ?? '') . '</div>';
        $badge = !empty($card['badge'])
            ? '<span class="stat-card__badge">' . Html::encode($card['badge']) . '</span>'
            : '';
        $body = $icon
            . $badge
            . '<div class="stat-card__value">' . Html::encode(number_format($card['value'])) . '</div>'
            . '<div class="stat-card__label">' . Html::encode($card['label']) . '</div>';
        ?>
        <?php if (!empty($card['url'])): ?>
            <?= Html::a($body, Url::to($card['url']), ['class' => 'stat-card']) ?>
        <?php else: ?>
            <div class="stat-card"><?= $body ?></div>
        <?php endif ?>
    <?php endforeach ?>
</div>
