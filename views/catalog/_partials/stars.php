<?php
/** @var float|string|null $value */
/** @var int $count */
/** @var string|null $href optional — wraps the stars in a link (e.g. "#customer-reviews") */
$v = $value !== null ? (float)$value : 0.0;
$full = (int)floor($v);
$href = $href ?? null;
?>
<?php if ($v > 0): ?>
    <?php $tag = $href !== null ? 'a' : 'span'; ?>
    <<?= $tag ?>
        <?php if ($href !== null): ?>href="<?= $href ?>"<?php endif; ?>
        class="inline-flex items-center gap-1 text-sm text-amber-500<?= $href !== null ? ' cursor-pointer' : '' ?>"
        aria-label="Rated <?= number_format($v, 1) ?> of 5<?= $href !== null ? ', jump to customer reviews' : '' ?>">
        <span aria-hidden="true"><?= str_repeat('★', max(0, min(5, $full))) . str_repeat('☆', max(0, 5 - $full)) ?></span>
        <span class="text-gray-600 font-medium"><?= number_format($v, 1) ?></span>
        <?php if ((int)($count ?? 0) > 0): ?><span class="text-gray-500">(<?= (int)$count ?>)</span><?php endif; ?>
    </<?= $tag ?>>
<?php endif; ?>
