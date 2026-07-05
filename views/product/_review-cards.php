<?php
/** @var array[] $cards Card DTOs from ReviewCardMapper */
/** @var int $imgBase Global image index offset (so lightbox indices are unique across appended pages) */

use yii\helpers\Html;

$starRow = static function (float $v): string {
    $pct = max(0.0, min(100.0, $v / 5 * 100));
    return '<span class="relative inline-block whitespace-nowrap leading-none text-xs" aria-hidden="true">'
        . '<span class="text-gray-200">★★★★★</span>'
        . '<span class="absolute inset-0 overflow-hidden text-amber-400" style="width:' . round($pct, 1) . '%">★★★★★</span>'
        . '</span>';
};

$gi = $imgBase;
foreach ($cards as $c): ?>
    <article class="rev-card">
        <div class="flex items-start gap-3">
            <span class="rev-avatar" style="background-color:<?= Html::encode($c['color']) ?>"><?= Html::encode($c['initial']) ?></span>
            <div class="min-w-0 flex-1">
                <div class="flex items-center gap-2">
                    <span class="truncate font-medium text-gray-900"><?= Html::encode($c['name']) ?></span>
                    <?php if ($c['flag'] !== ''): ?><span class="text-base leading-none"><?= $c['flag'] ?></span><?php endif; ?>
                </div>
                <div class="mt-0.5 flex items-center gap-2">
                    <?= $starRow((float) $c['rating']) ?>
                    <?php if ($c['date'] !== ''): ?><span class="text-xs text-gray-400"><?= Html::encode($c['date']) ?></span><?php endif; ?>
                </div>
            </div>
        </div>
        <?php if ($c['content'] !== ''): ?>
            <p class="mt-3 text-sm leading-relaxed text-gray-700" style="text-wrap: pretty;"><?= Html::encode($c['content']) ?></p>
        <?php endif; ?>
        <?php if ($c['images']): ?>
            <div class="mt-3 flex flex-wrap gap-2">
                <?php foreach ($c['images'] as $url): ?>
                    <button type="button" data-lb="<?= $gi ?>" class="rev-thumb" aria-label="Open review photo">
                        <img src="<?= Html::encode($url) ?>" alt="" loading="lazy">
                    </button>
                    <?php $gi++; ?>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </article>
<?php endforeach; ?>
