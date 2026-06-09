<?php
/** @var yii\data\Pagination $pages */
use yii\helpers\Url;
if ($pages->pageCount <= 1) return;
$cur = $pages->page;
?>
<nav class="mt-8 flex flex-wrap justify-center gap-1">
    <?php for ($p = 0; $p < $pages->pageCount; $p++): ?>
        <?php if ($p === $cur): ?>
            <span class="rounded-md bg-[color:var(--accent)] px-3 py-1 text-sm text-white"><?= $p + 1 ?></span>
        <?php else: ?>
            <a class="rounded-md border border-gray-300 px-3 py-1 text-sm hover:bg-gray-100" href="<?= Url::current(['page' => $p + 1]) ?>"><?= $p + 1 ?></a>
        <?php endif; ?>
    <?php endfor; ?>
</nav>
