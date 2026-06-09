<?php
/** @var float|string|null $value */
/** @var int $count */
$v = $value !== null ? (float)$value : 0.0;
$full = (int)floor($v);
?>
<?php if ($v > 0): ?>
    <span class="inline-flex items-center gap-1 text-sm text-amber-500" aria-label="Rated <?= number_format($v, 1) ?> of 5">
        <span aria-hidden="true"><?= str_repeat('★', max(0, min(5, $full))) . str_repeat('☆', max(0, 5 - $full)) ?></span>
        <span class="text-gray-500">(<?= (int)($count ?? 0) ?>)</span>
    </span>
<?php endif; ?>
