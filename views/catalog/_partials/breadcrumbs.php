<?php
/** @var array<int,array{name:string,url:?string}> $items */
use yii\helpers\Html;
?>
<nav class="mb-4 text-sm text-gray-500">
    <ol class="flex flex-wrap items-center gap-1">
        <?php foreach ($items as $i => $item): ?>
            <li class="flex items-center gap-1">
                <?php if (!empty($item['url'])): ?>
                    <a class="hover:text-[color:var(--accent)]" href="<?= Html::encode($item['url']) ?>"><?= Html::encode($item['name']) ?></a>
                <?php else: ?>
                    <span class="text-gray-700"><?= Html::encode($item['name']) ?></span>
                <?php endif; ?>
                <?php if ($i < count($items) - 1): ?><span aria-hidden="true">/</span><?php endif; ?>
            </li>
        <?php endforeach; ?>
    </ol>
</nav>
