<?php

declare(strict_types=1);

use yii\helpers\Html;
use yii\helpers\Url;

/**
 * Reusable admin list filter bar: a name search box plus an optional status dropdown.
 * Submits via GET so the current filter is bookmarkable and survives pagination.
 *
 * @var yii\web\View $this
 * @var string $action route the form submits to, e.g. 'index'
 * @var string $q current search term
 * @var string $status currently selected status value ('' = all)
 * @var array<string,string> $statuses value => label options (empty to hide the dropdown)
 * @var string $placeholder search input placeholder
 * @var array<string,int|string> $hidden extra query params to preserve (e.g. store_id)
 */

$statuses ??= [];
$hidden ??= [];
$placeholder ??= 'Search by name…';
$hasFilter = trim($q) !== '' || $status !== '';
?>
<form method="get" action="<?= Html::encode(Url::to([$action])) ?>" class="row g-2 align-items-center mb-3">
    <?php foreach ($hidden as $name => $value): ?>
        <?= Html::hiddenInput($name, (string)$value) ?>
    <?php endforeach; ?>
    <div class="col-12 col-sm">
        <?= Html::input('search', 'q', $q, [
            'class' => 'form-control',
            'placeholder' => $placeholder,
            'aria-label' => 'Search by name',
        ]) ?>
    </div>
    <?php if ($statuses !== []): ?>
        <div class="col-6 col-sm-auto">
            <?= Html::dropDownList('status', $status, ['' => 'All statuses'] + $statuses, [
                'class' => 'form-select',
                'aria-label' => 'Filter by status',
            ]) ?>
        </div>
    <?php endif; ?>
    <div class="col-auto">
        <button type="submit" class="btn btn-primary">Filter</button>
    </div>
    <?php if ($hasFilter): ?>
        <div class="col-auto">
            <?= Html::a('Clear', array_merge([$action], $hidden), ['class' => 'btn btn-outline-secondary']) ?>
        </div>
    <?php endif; ?>
</form>
