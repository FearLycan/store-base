<?php
/** @var array $current */
use yii\helpers\Html;
$sortLabels = ['popular' => 'Most popular', 'newest' => 'Newest', 'price_asc' => 'Price ↑', 'price_desc' => 'Price ↓', 'rating' => 'Top rated'];
?>
<form method="get" class="mb-6 flex flex-wrap items-end gap-3 rounded-xl border border-gray-200 bg-white p-4">
    <?php foreach (['q', 'category'] as $keep): ?>
        <?php if (!empty($current[$keep])): ?><input type="hidden" name="<?= $keep ?>" value="<?= Html::encode((string)$current[$keep]) ?>"><?php endif; ?>
    <?php endforeach; ?>
    <label class="text-sm">Min $<br><input type="number" step="0.01" name="min" value="<?= Html::encode((string)($current['min'] ?? '')) ?>" class="w-24 rounded border border-gray-300 px-2 py-1"></label>
    <label class="text-sm">Max $<br><input type="number" step="0.01" name="max" value="<?= Html::encode((string)($current['max'] ?? '')) ?>" class="w-24 rounded border border-gray-300 px-2 py-1"></label>
    <label class="text-sm">Min rating<br>
        <select name="rating" class="rounded border border-gray-300 px-2 py-1">
            <option value="">Any</option>
            <?php foreach ([4, 3, 2] as $r): ?><option value="<?= $r ?>" <?= (string)($current['rating'] ?? '') === (string)$r ? 'selected' : '' ?>><?= $r ?>+</option><?php endforeach; ?>
        </select>
    </label>
    <label class="text-sm">Sort<br>
        <select name="sort" class="rounded border border-gray-300 px-2 py-1">
            <?php foreach ($sortLabels as $val => $label): ?><option value="<?= $val ?>" <?= (string)($current['sort'] ?? 'popular') === $val ? 'selected' : '' ?>><?= $label ?></option><?php endforeach; ?>
        </select>
    </label>
    <button type="submit" class="rounded-lg bg-[color:var(--accent)] px-4 py-2 text-sm font-semibold text-white">Apply</button>
</form>
