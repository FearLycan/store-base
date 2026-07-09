<?php
/**
 * Store logo tile: the store's image, or an accent monogram fallback when it has none.
 * Single source of truth for how a store is represented across the storefront.
 *
 * The monogram is drawn as an SVG glyph (dominant-baseline: central) rather than a
 * flex-centered letter: it stays optically centered regardless of font metrics or
 * tile size, and the letter scales with the tile automatically.
 *
 * @var app\models\Store $store
 * @var string $size One of: sm (product "Sold by"), md (store card), lg (store hero), xl (store page identity header).
 */

use yii\helpers\Html;

$size = $size ?? 'md';
$presets = [
    'sm' => ['box' => 'h-12 w-12', 'round' => 'rounded-lg'],
    'md' => ['box' => 'h-14 w-14', 'round' => 'rounded-xl'],
    'lg' => ['box' => 'h-16 w-16', 'round' => 'rounded-2xl'],
    'xl' => ['box' => 'h-20 w-20', 'round' => 'rounded-2xl'],
];
$p = $presets[$size] ?? $presets['md'];
$image = trim((string) $store->image_url);
?>
<?php if ($image !== ''): ?>
    <img src="<?= Html::encode($image) ?>" alt="<?= Html::encode($store->name) ?>"
         class="<?= $p['box'] ?> flex-none <?= $p['round'] ?> border border-gray-200 bg-white object-cover" loading="lazy">
<?php else: ?>
    <?php $initial = preg_match('~[\p{L}\p{N}]~u', $store->name, $m) === 1 ? mb_strtoupper($m[0]) : '?'; ?>
    <span class="<?= $p['box'] ?> flex-none select-none <?= $p['round'] ?> bg-[color:var(--accent)] text-white">
        <svg viewBox="0 0 100 100" class="h-full w-full" aria-hidden="true">
            <text x="50" y="50" text-anchor="middle" dominant-baseline="central"
                  fill="currentColor" font-size="56" font-weight="700" font-family="inherit"><?= Html::encode($initial) ?></text>
        </svg>
    </span>
<?php endif; ?>
