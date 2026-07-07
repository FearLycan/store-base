<?php
/** @var yii\data\Pagination $pages */
use yii\helpers\Url;
if ($pages->pageCount <= 1) return;

$cur   = $pages->page;          // 0-indexed current page
$last  = $pages->pageCount - 1; // 0-indexed last page
$window = 2;                    // pages to show on each side of the current one

// Build the windowed list of page numbers with gaps marked as null.
$nums = [];
$from = max(0, $cur - $window);
$to   = min($last, $cur + $window);
if ($from > 0) {
    $nums[] = 0;
    if ($from > 1) $nums[] = null; // left ellipsis
}
for ($p = $from; $p <= $to; $p++) $nums[] = $p;
if ($to < $last) {
    if ($to < $last - 1) $nums[] = null; // right ellipsis
    $nums[] = $last;
}

$navClass  = 'rounded-md border border-gray-300 px-3 py-1 text-sm hover:bg-gray-100';
$dimClass  = 'rounded-md border border-gray-200 px-3 py-1 text-sm text-gray-300 cursor-not-allowed';

// Page 1 links to the clean, param-free URL so it can't split with the canonical
// over a stray `?page=1`; every other page carries its 1-based `page` param.
$url = static fn (int $p): string => Url::current(['page' => $p <= 0 ? null : $p + 1]);
?>
<nav class="mt-8 flex flex-wrap items-center justify-center gap-1" aria-label="Pagination">
    <?php if ($cur > 0): ?>
        <a class="<?= $navClass ?>" href="<?= $url(0) ?>" aria-label="First">&laquo;</a>
        <a class="<?= $navClass ?>" href="<?= $url($cur - 1) ?>" rel="prev" aria-label="Previous">&lsaquo;</a>
    <?php else: ?>
        <span class="<?= $dimClass ?>" aria-hidden="true">&laquo;</span>
        <span class="<?= $dimClass ?>" aria-hidden="true">&lsaquo;</span>
    <?php endif; ?>

    <?php foreach ($nums as $p): ?>
        <?php if ($p === null): ?>
            <span class="px-2 py-1 text-sm text-gray-400">&hellip;</span>
        <?php elseif ($p === $cur): ?>
            <span class="rounded-md bg-[color:var(--accent)] px-3 py-1 text-sm text-white" aria-current="page"><?= $p + 1 ?></span>
        <?php else: ?>
            <a class="<?= $navClass ?>" href="<?= $url($p) ?>"><?= $p + 1 ?></a>
        <?php endif; ?>
    <?php endforeach; ?>

    <?php if ($cur < $last): ?>
        <a class="<?= $navClass ?>" href="<?= $url($cur + 1) ?>" rel="next" aria-label="Next">&rsaquo;</a>
        <a class="<?= $navClass ?>" href="<?= $url($last) ?>" aria-label="Last">&raquo;</a>
    <?php else: ?>
        <span class="<?= $dimClass ?>" aria-hidden="true">&rsaquo;</span>
        <span class="<?= $dimClass ?>" aria-hidden="true">&raquo;</span>
    <?php endif; ?>
</nav>
