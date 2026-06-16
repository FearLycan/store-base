<?php
/** @var yii\web\View $this */
/** @var array<int, array{q?: string, a?: string}> $faq */
use yii\helpers\Html;

$faq = $faq ?? [];
$rows = [];
foreach ($faq as $row) {
    $q = trim((string) ($row['q'] ?? ''));
    $a = trim((string) ($row['a'] ?? ''));
    if ($q !== '' && $a !== '') { $rows[] = ['q' => $q, 'a' => $a]; }
}
if ($rows === []) { return; }
?>
<section class="mt-12">
    <h2 class="mb-4 text-xl font-bold" style="text-wrap: balance;">Frequently asked questions</h2>
    <div class="divide-y divide-gray-100 overflow-hidden rounded-xl border border-gray-200 bg-white">
        <?php foreach ($rows as $row): ?>
        <div x-data="{ open: false }">
            <button type="button" @click="open = !open" class="flex w-full items-center justify-between gap-4 px-4 py-3.5 text-left" :aria-expanded="open">
                <span class="text-sm font-semibold text-gray-900"><?= Html::encode($row['q']) ?></span>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4 flex-none text-gray-400 transition-transform duration-200" :class="{ '-rotate-180': open }"><polyline points="6 9 12 15 18 9"/></svg>
            </button>
            <div x-show="open" x-cloak class="px-4 pb-4 text-sm leading-relaxed text-gray-700" style="text-wrap: pretty;"><?= Html::encode($row['a']) ?></div>
        </div>
        <?php endforeach; ?>
    </div>
</section>
