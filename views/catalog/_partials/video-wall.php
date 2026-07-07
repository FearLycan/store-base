<?php
/** @var app\models\Product[] $videos */
use yii\helpers\Url;

if ($videos === []) {
    return;
}

$arrow = '<svg viewBox="0 0 16 16" fill="none" aria-hidden="true" class="h-3.5 w-3.5"><path d="M6 3.5 10.5 8 6 12.5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>';
?>
<section class="mb-12">
    <div class="mb-4 flex items-baseline justify-between gap-4">
        <div>
            <h2 class="text-xl font-bold tracking-tight">See it in action</h2>
            <p class="mt-1 text-sm text-gray-500">Real product clips — tap to watch, then jump straight to the item.</p>
        </div>
        <a href="<?= Url::to(['/catalog/videos']) ?>" class="rail-more">View all <?= $arrow ?></a>
    </div>

    <?= $this->render('video-player', ['videos' => $videos, 'layout' => 'rail']) ?>
</section>
