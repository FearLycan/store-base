<?php
/** @var yii\web\View $this */
/** @var app\models\Product $product */
/** @var app\models\Product[] $related */
use app\components\Seo;
use app\components\schema\builder\ProductPageSchemaBuilder;
use app\components\schema\JsonLdRenderer;
use yii\helpers\Html;
use yii\helpers\Json;
use yii\helpers\Url;

$canonical = Url::to(['/product/view', 'slug' => $product->slug], true);
$descText = $product->description !== null ? trim(strip_tags((string)$product->description)) : $product->displayName;
Seo::apply($this, $product->displayName, $descText !== '' ? $descText : $product->displayName, $canonical, false, (string)$product->main_image);

$images = [];
foreach ($product->images as $img) { $images[] = $img->url; }
if ($images === [] && $product->main_image) { $images[] = $product->main_image; }
if ($images === []) { $images[] = '/img/placeholder.png'; }

// The raw "detail" HTML is a vendor blob: a tall stack of marketing graphics
// plus the odd spec line and cross-sell link spam. Restructure it into clean
// highlights + a de-duped image lookbook so we can present it on our terms.
$desc = \app\components\ProductDescription::parse($product->description, $images);
$descImages = $desc['images'];
$descHighlights = $desc['highlights'];
$descCollapsed = count($descImages) <= 9 ? count($descImages) : 6;

$goUrl = Url::to(['/go/index', 'id' => $product->id]);
$crumbLinks = [];
if ($product->category !== null) {
    $crumbLinks[] = ['label' => $product->category->name, 'url' => Url::to(['/catalog/category', 'slug' => $product->category->slug])];
}

// --- Variant selector data ------------------------------------------------
// Group SKU variants by their option dimensions (e.g. "Metal Color", size). A
// value earns a swatch image only when every variant carrying it shares the
// same image — so colours get thumbnails while sizes stay as plain pills.
$groupNames = [];
$valueOrder = [];   // [name] => values in first-seen order
$valueImg   = [];   // [name][value] => image|null (null once images conflict)
$valueSeen  = [];   // [name][value] => true
$jsVariants = [];   // flat list handed to Alpine

foreach ($product->variants as $v) {
    $opts = is_array($v->options_json) ? $v->options_json : [];
    $clean = [];
    foreach ($opts as $name => $val) {
        $name = (string)$name;
        if ($name === '' || $name[0] === '_') { continue; } // skip _sku_attr et al.
        $val = trim((string)$val);
        if ($val === '') { continue; }
        $clean[$name] = $val;
        if (!in_array($name, $groupNames, true)) { $groupNames[] = $name; $valueOrder[$name] = []; }
        $img = ($v->image !== null && $v->image !== '') ? (string)$v->image : null;
        if (!isset($valueSeen[$name][$val])) {
            $valueSeen[$name][$val] = true;
            $valueOrder[$name][] = $val;
            $valueImg[$name][$val] = $img;
        } elseif (($valueImg[$name][$val] ?? null) !== $img) {
            $valueImg[$name][$val] = null;
        }
    }
    $jsVariants[] = [
        'opts'   => (object)$clean,
        'price'  => $v->price !== null ? (int)$v->price : null,
        'oprice' => $v->original_price !== null ? (int)$v->original_price : null,
        'stock'  => $v->stock !== null ? (int)$v->stock : null,
        'image'  => ($v->image !== null && $v->image !== '') ? (string)$v->image : null,
    ];
}

// AliExpress often mislabels a property (e.g. sizes filed under "Main Stone
// Color"). Resolve a friendlier display label from the values while keeping the
// raw property name as the matching key. Order matters: first match wins.
$prettyLabel = static function (string $name, array $vals): string {
    $patterns = [
        'Size'   => '~^\d+(?:\.\d+)?\s*(?:mm|cm|inch|in|")$~i',
        'Length' => '~^\d+(?:\.\d+)?\s*(?:m|ft)$~i',
    ];
    foreach ($patterns as $label => $pattern) {
        $matches = array_filter($vals, static fn ($x): bool => preg_match($pattern, trim((string)$x)) === 1);
        if (count($matches) >= max(1, (int)ceil(count($vals) * 0.6))) {
            return $label;
        }
    }

    return $name;
};

$groups = [];
foreach ($groupNames as $name) {
    $hasImage = false;
    $values = [];
    foreach ($valueOrder[$name] as $val) {
        $img = $valueImg[$name][$val] ?? null;
        if ($img !== null) { $hasImage = true; }
        $values[] = ['value' => $val, 'image' => $img];
    }
    $groups[] = [
        'name'    => $name,
        'label'   => $prettyLabel($name, $valueOrder[$name]),
        'values'  => $values,
        'hasImage' => $hasImage,
    ];
}
// Image-bearing groups first (swatches), label-only groups after (pills).
usort($groups, static fn ($a, $b): int => $b['hasImage'] <=> $a['hasImage']);

// Preselect the first in-stock variant (fall back to the first) so the page
// opens on a coherent image + price, matching the SSR-seeded values below.
$defaultVariant = null;
foreach ($product->variants as $v) {
    if ((int)$v->stock > 0) { $defaultVariant = $v; break; }
}
if ($defaultVariant === null && $product->variants) { $defaultVariant = $product->variants[0]; }

$defaultSelected = [];
if ($defaultVariant !== null && is_array($defaultVariant->options_json)) {
    foreach ($defaultVariant->options_json as $name => $val) {
        $name = (string)$name;
        if ($name === '' || $name[0] === '_') { continue; }
        $val = trim((string)$val);
        if ($val !== '') { $defaultSelected[$name] = $val; }
    }
}

$seedPrice    = ($defaultVariant && $defaultVariant->price !== null) ? (int)$defaultVariant->price : $product->price;
$seedOprice   = $defaultVariant ? ($defaultVariant->original_price !== null ? (int)$defaultVariant->original_price : null) : $product->original_price;
$seedCurrency = $product->currency_code ?: 'USD';
$seedDiscount = ($seedPrice && $seedOprice && $seedOprice > $seedPrice) ? (int)round((1 - $seedPrice / $seedOprice) * 100) : null;
$defaultImage = ($defaultVariant && $defaultVariant->image) ? (string)$defaultVariant->image : $images[0];

$cfg = [
    'images'   => $images,
    'hasVideo' => (bool)$product->video_url,
    'groups'   => array_map(static fn ($g): array => [
        'name'   => $g['name'],
        'values' => array_map(static fn ($x): array => ['value' => $x['value'], 'image' => $x['image']], $g['values']),
    ], $groups),
    'variants' => $jsVariants,
    'base'     => ['price' => $product->price, 'oprice' => $product->original_price, 'currency' => $seedCurrency],
    'selected' => (object)$defaultSelected,
    'mainImage' => $defaultImage,
];
?>
<?= JsonLdRenderer::render(ProductPageSchemaBuilder::build($product, $canonical, $goUrl, $crumbLinks, ['label' => 'Home', 'url' => Url::to(['/catalog/index'])])) ?>

<?= $this->render('//catalog/_partials/breadcrumbs', ['items' => array_values(array_filter([
    ['name' => 'Home', 'url' => Url::to(['/catalog/index'])],
    $product->category ? ['name' => $product->category->name, 'url' => Url::to(['/catalog/category', 'slug' => $product->category->slug])] : null,
    ['name' => $product->displayName, 'url' => null],
]))]) ?>

<div class="grid gap-8 lg:grid-cols-2" x-data="productView(<?= Html::encode(Json::encode($cfg)) ?>)">
    <div>
        <div class="group relative aspect-square select-none overflow-hidden rounded-xl border border-gray-200 bg-white">
            <?php if ($product->video_url): ?>
            <video x-show="showVideo" x-cloak controls preload="none" poster="<?= Html::encode($images[0]) ?>" class="h-full w-full object-contain">
                <source src="<?= Html::encode((string)$product->video_url) ?>" type="video/mp4">
            </video>
            <?php endif; ?>
            <img x-show="!showVideo" :src="main" alt="<?= Html::encode($product->displayName) ?>" class="h-full w-full object-contain transition-opacity duration-150" loading="lazy">
            <?php if (count($images) > 1): ?>
            <button type="button" @click="prev()" x-show="!showVideo" x-cloak class="pdp-nav left-2" aria-label="Previous image">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-5 w-5"><polyline points="15 18 9 12 15 6"></polyline></svg>
            </button>
            <button type="button" @click="next()" x-show="!showVideo" x-cloak class="pdp-nav right-2" aria-label="Next image">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-5 w-5"><polyline points="9 18 15 12 9 6"></polyline></svg>
            </button>
            <div x-show="!showVideo" x-cloak class="pointer-events-none absolute bottom-2 left-1/2 -translate-x-1/2 rounded-full bg-black/55 px-2.5 py-1 text-xs font-medium tabular-nums text-white opacity-0 transition-opacity group-hover:opacity-100">
                <span x-text="imageIndex + 1"></span>/<?= count($images) ?>
            </div>
            <?php endif; ?>
        </div>
        <?php if (count($images) > 1 || $product->video_url): ?>
        <div class="mt-3 flex gap-2 overflow-x-auto pb-1">
            <?php if ($product->video_url): ?>
                <button type="button" @click="showVideo = true" class="pdp-thumb" :class="{ 'is-active': showVideo }" aria-label="Play video">
                    <img src="<?= Html::encode($images[0]) ?>" alt="" loading="lazy" class="h-full w-full object-cover opacity-70">
                    <span class="absolute inset-0 flex items-center justify-center text-white drop-shadow">▶</span>
                </button>
            <?php endif; ?>
            <?php foreach ($images as $img): ?>
                <button type="button" @click="setMain(<?= Html::encode(Json::encode($img)) ?>)" class="pdp-thumb" :class="{ 'is-active': !showVideo && main === <?= Html::encode(Json::encode($img)) ?> }">
                    <img src="<?= Html::encode($img) ?>" alt="" loading="lazy" class="h-full w-full object-cover">
                </button>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

    <div>
        <h1 class="text-2xl font-bold leading-snug"><?= Html::encode($product->displayName) ?></h1>

        <div class="mt-2 flex flex-wrap items-center gap-x-3 gap-y-1">
            <?= $this->render('//catalog/_partials/stars', ['value' => $product->rating_value, 'count' => $product->review_count]) ?>
            <?php if ((int)$product->orders_count > 0): ?>
                <span class="text-sm text-gray-500"><?= number_format((int)$product->orders_count) ?> sold</span>
            <?php endif; ?>
        </div>

        <?php if ($groups): ?>
        <!-- Price reflects the selected variant -->
        <div class="mt-4 flex flex-wrap items-baseline gap-2">
            <span class="text-3xl font-bold tabular-nums text-gray-900"><span x-text="priceText"><?= $seedPrice !== null ? Html::encode(number_format($seedPrice / 100, 2)) : '' ?></span> <span class="text-lg font-semibold text-gray-500"><?= Html::encode($seedCurrency) ?></span></span>
            <span x-show="opriceText" x-cloak class="text-base text-gray-400 line-through tabular-nums" x-text="opriceText"><?= $seedOprice !== null ? Html::encode(number_format($seedOprice / 100, 2)) : '' ?></span>
            <span x-show="discount" x-cloak class="pdp-discount">−<span x-text="discount"><?= $seedDiscount ?></span>%</span>
        </div>
        <!-- Reserve the line height so toggling the stock note never shifts the layout. -->
        <div class="mt-1.5 min-h-[1.25rem]">
            <p x-show="lowStock" x-cloak class="text-sm font-medium text-amber-600">Only <span x-text="stock"></span> left in stock</p>
        </div>
        <?php else: ?>
        <div class="mt-4"><?= $this->render('//catalog/_partials/price', ['product' => $product]) ?></div>
        <?php endif; ?>

        <?php if ($product->availability !== null && stripos((string)$product->availability, 'out') !== false): ?>
            <p class="mt-2 inline-block rounded bg-amber-100 px-2 py-1 text-sm text-amber-800">Currently unavailable</p>
        <?php endif; ?>

        <?php foreach ($groups as $gi => $g): ?>
        <div class="mt-6">
            <div class="mb-2 flex items-center gap-1.5 text-sm">
                <span class="font-semibold text-gray-900"><?= Html::encode($g['label']) ?>:</span>
                <span class="text-gray-500" x-text="selected[groups[<?= $gi ?>].name] || 'Select'"></span>
            </div>
            <?php if ($g['hasImage']): ?>
            <div class="flex flex-wrap gap-2">
                <?php foreach ($g['values'] as $vi => $val): ?>
                    <button type="button" @click="pick(<?= $gi ?>, <?= $vi ?>)"
                            class="variant-swatch" :class="{ 'is-active': isSel(<?= $gi ?>, <?= $vi ?>), 'is-out': !avail(<?= $gi ?>, <?= $vi ?>) }"
                            title="<?= Html::encode((string)$val['value']) ?>">
                        <?php if ($val['image']): ?>
                            <img src="<?= Html::encode($val['image']) ?>" alt="<?= Html::encode((string)$val['value']) ?>" loading="lazy">
                        <?php else: ?>
                            <span><?= Html::encode((string)$val['value']) ?></span>
                        <?php endif; ?>
                    </button>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <div class="flex flex-wrap gap-2">
                <?php foreach ($g['values'] as $vi => $val): ?>
                    <button type="button" @click="pick(<?= $gi ?>, <?= $vi ?>)"
                            class="variant-pill" :class="{ 'is-active': isSel(<?= $gi ?>, <?= $vi ?>), 'is-out': !avail(<?= $gi ?>, <?= $vi ?>) }">
                        <?= Html::encode((string)$val['value']) ?>
                    </button>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>

        <a href="<?= $goUrl ?>" target="_blank" rel="nofollow sponsored noopener" class="btn-accent mt-6 w-full">View on AliExpress →</a>
        <p class="mt-2 text-xs text-gray-400">Price/availability on AliExpress may differ.<?php if ($product->last_price_synced_at): ?> Updated <?= Yii::$app->formatter->asRelativeTime($product->last_price_synced_at) ?>.<?php endif; ?></p>
    </div>
</div>

<?php if ($product->specs): ?>
<section class="mt-10" x-data="{ open: false }">
    <h2 class="mb-3 text-xl font-bold">Specifications</h2>
    <div class="overflow-hidden rounded-xl border border-gray-200 bg-white">
        <dl class="grid grid-cols-1 sm:grid-cols-2">
            <?php foreach ($product->specs as $i => $a): ?>
                <div class="flex justify-between gap-4 border-b border-gray-100 px-4 py-2.5 text-sm"<?= $i >= 8 ? ' x-show="open" x-cloak' : '' ?>>
                    <dt class="text-gray-500"><?= Html::encode($a->name) ?></dt>
                    <dd class="text-right font-medium text-gray-800"><?= Html::encode((string)$a->value) ?></dd>
                </div>
            <?php endforeach; ?>
        </dl>
        <?php if (count($product->specs) > 8): ?>
        <button @click="open = !open" class="flex w-full items-center justify-center gap-1 px-4 py-2.5 text-sm font-semibold text-[color:var(--accent)] transition hover:bg-gray-50">
            <span x-text="open ? 'Show less' : 'Show all <?= count($product->specs) ?> specifications'"></span>
            <span class="text-[10px]" x-text="open ? '▲' : '▼'"></span>
        </button>
        <?php endif; ?>
    </div>
</section>
<?php endif; ?>

<?php if ($descHighlights || $descImages): ?>
<section class="mt-10" x-data="productDesc(<?= Html::encode(Json::encode(['images' => $descImages, 'collapsed' => $descCollapsed])) ?>)">
    <h2 class="mb-4 text-xl font-bold">Product details</h2>

    <?php if ($descHighlights): ?>
    <div class="desc-highlights">
        <div class="mb-3 flex items-center gap-2 text-sm font-semibold text-gray-900">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4 text-[color:var(--accent)]"><path d="M12 2 9.2 8.6 2 9.2l5.5 4.7L5.8 21 12 17.3 18.2 21l-1.7-7.1L22 9.2l-7.2-.6z"/></svg>
            Highlights
        </div>
        <ul class="space-y-2">
            <?php foreach ($descHighlights as $h): ?>
            <li class="flex gap-2.5 text-sm leading-relaxed text-gray-700" style="text-wrap: pretty;">
                <span class="mt-2 h-1.5 w-1.5 flex-none rounded-full bg-[color:var(--accent)]"></span>
                <span><?= Html::encode($h) ?></span>
            </li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>

    <?php if ($descImages): ?>
    <div class="<?= $descHighlights ? 'mt-5' : '' ?> desc-grid">
        <?php foreach ($descImages as $i => $im): ?>
        <?php $wide = $im['w'] && $im['h'] && ($im['w'] / $im['h']) >= 1.5; ?>
        <figure @click="open(<?= $i ?>)"
                <?php if ($i >= $descCollapsed): ?>x-show="expanded" x-cloak
                x-transition:enter="transition duration-300 ease-out"
                x-transition:enter-start="opacity-0 translate-y-2"
                x-transition:enter-end="opacity-100 translate-y-0"
                :style="`transition-delay:${Math.min(<?= $i - $descCollapsed ?>, 6) * 55}ms`"<?php endif; ?>
                class="desc-shot<?= $wide ? ' is-wide' : '' ?>"
                <?php if ($im['w'] && $im['h']): ?>style="aspect-ratio:<?= (int)$im['w'] ?>/<?= (int)$im['h'] ?>"<?php endif; ?>>
            <!-- Wide banners that lack server-side dimensions break out to full width once their natural ratio is known. -->
            <img src="<?= Html::encode($im['url']) ?>" alt="" loading="lazy" class="block h-full w-full object-cover"
                 @load="$el.naturalWidth / $el.naturalHeight >= 1.5 && $el.parentElement.classList.add('is-wide')">
            <span class="desc-zoom" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3M11 8v6M8 11h6"/></svg>
            </span>
        </figure>
        <?php endforeach; ?>
    </div>

    <?php if ($descCollapsed < count($descImages)): ?>
    <button type="button" @click="expanded = true" x-show="!expanded" class="desc-more">
        <span>Show more</span>
        <span class="tabular-nums text-gray-400">(<span x-text="hidden"><?= count($descImages) - $descCollapsed ?></span>)</span>
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4"><polyline points="6 9 12 15 18 9"/></svg>
    </button>
    <?php endif; ?>
    <?php endif; ?>

    <!-- Lightbox -->
    <div x-show="lightbox" x-cloak @keydown.escape.window="close()"
         @keydown.arrow-right.window="lightbox && next()" @keydown.arrow-left.window="lightbox && prev()"
         x-effect="document.body.style.overflow = lightbox ? 'hidden' : ''"
         x-transition:enter="transition duration-200 ease-out" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
         x-transition:leave="transition duration-150 ease-in" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
         class="fixed inset-0 z-50 flex items-center justify-center bg-black/85 p-4 backdrop-blur-sm" @click.self="close()">
        <button type="button" @click="close()" class="lb-btn absolute right-3 top-3" aria-label="Close">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-5 w-5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
        </button>
        <button type="button" @click.stop="prev()" class="lb-btn absolute left-3 top-1/2 -translate-y-1/2" aria-label="Previous">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-6 w-6"><polyline points="15 18 9 12 15 6"/></svg>
        </button>
        <img :src="images[lbIndex]?.url" alt="" class="max-h-[88vh] max-w-full rounded-lg object-contain shadow-2xl" @click.stop>
        <button type="button" @click.stop="next()" class="lb-btn absolute right-3 top-1/2 -translate-y-1/2" aria-label="Next">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-6 w-6"><polyline points="9 18 15 12 9 6"/></svg>
        </button>
        <div class="absolute bottom-4 left-1/2 -translate-x-1/2 rounded-full bg-white/10 px-3 py-1 text-sm font-medium tabular-nums text-white/90"><span x-text="lbIndex + 1"></span> / <span x-text="images.length"></span></div>
    </div>
</section>
<?php endif; ?>

<?php if ($product->reviews): ?>
<section class="mt-10">
    <h2 class="mb-3 text-xl font-bold">Reviews</h2>
    <div class="space-y-4">
        <?php foreach (array_slice($product->reviews, 0, 20) as $r): ?>
            <article class="rounded-xl border border-gray-200 bg-white p-4">
                <div class="flex items-center justify-between">
                    <span class="font-medium"><?= Html::encode((string)($r->author_name ?: 'Anonymous')) ?><?= $r->reviewer_country ? ' · ' . Html::encode($r->reviewer_country) : '' ?></span>
                    <?= $this->render('//catalog/_partials/stars', ['value' => $r->rating_value, 'count' => 0]) ?>
                </div>
                <?php if ($r->content): ?><p class="mt-2 text-sm text-gray-700"><?= Html::encode((string)$r->content) ?></p><?php endif; ?>
                <?php if ($r->images): ?><div class="mt-2 flex gap-2"><?php foreach ($r->images as $ri): ?><img src="<?= Html::encode($ri->url) ?>" alt="" loading="lazy" class="h-16 w-16 rounded object-cover"><?php endforeach; ?></div><?php endif; ?>
            </article>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

<?php if ($related): ?>
<section class="mt-10">
    <h2 class="mb-4 text-xl font-bold">You may also like</h2>
    <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4">
        <?php foreach ($related as $p): ?><?= $this->render('//catalog/_partials/product-card', ['product' => $p]) ?><?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

<?php
$this->registerJs(<<<'JS'
document.addEventListener('alpine:init', () => {
    Alpine.data('productView', (cfg) => ({
        images: cfg.images,
        groups: cfg.groups,
        variants: cfg.variants,
        base: cfg.base,
        hasVideo: cfg.hasVideo,
        selected: Object.assign({}, cfg.selected || {}),
        main: cfg.mainImage || cfg.images[0],
        showVideo: false,

        setMain(src) { this.main = src; this.showVideo = false; },

        get imageIndex() { const i = this.images.indexOf(this.main); return i < 0 ? 0 : i; },
        next() { this.main = this.images[(this.imageIndex + 1) % this.images.length]; this.showVideo = false; },
        prev() { this.main = this.images[(this.imageIndex - 1 + this.images.length) % this.images.length]; this.showVideo = false; },

        pick(gi, vi) {
            const g = this.groups[gi];
            const val = g.values[vi].value;
            this.selected[g.name] = (this.selected[g.name] === val) ? null : val;
            this.syncImage();
        },

        isSel(gi, vi) {
            const g = this.groups[gi];
            return this.selected[g.name] === g.values[vi].value;
        },

        // Is this value still buyable given the OTHER currently-selected dimensions?
        avail(gi, vi) {
            const g = this.groups[gi];
            const val = g.values[vi].value;
            return this.variants.some((v) => {
                if (v.opts[g.name] !== val) { return false; }
                for (let i = 0; i < this.groups.length; i++) {
                    if (i === gi) { continue; }
                    const sel = this.selected[this.groups[i].name];
                    if (sel && v.opts[this.groups[i].name] !== sel) { return false; }
                }
                return (v.stock ?? 0) > 0;
            });
        },

        matched() {
            if (!this.groups.length) { return null; }
            for (const g of this.groups) { if (!this.selected[g.name]) { return null; } }
            return this.variants.find((v) => this.groups.every((g) => v.opts[g.name] === this.selected[g.name])) || null;
        },

        syncImage() {
            const m = this.matched();
            if (m && m.image) { this.main = m.image; this.showVideo = false; return; }
            for (const g of this.groups) {
                const sel = this.selected[g.name];
                if (!sel) { continue; }
                const v = g.values.find((x) => x.value === sel);
                if (v && v.image) { this.main = v.image; this.showVideo = false; return; }
            }
        },

        fmt(c) {
            return (c == null) ? null : (c / 100).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        },
        get priceText() { const m = this.matched(); return this.fmt(m ? m.price : this.base.price); },
        get opriceText() {
            const m = this.matched();
            const o = m ? m.oprice : this.base.oprice;
            const p = m ? m.price : this.base.price;
            return (o && p && o > p) ? this.fmt(o) : null;
        },
        get discount() {
            const m = this.matched();
            const o = m ? m.oprice : this.base.oprice;
            const p = m ? m.price : this.base.price;
            return (o && p && o > p) ? Math.round((1 - p / o) * 100) : null;
        },
        get stock() { const m = this.matched(); return m ? m.stock : null; },
        get lowStock() { const s = this.stock; return s != null && s > 0 && s <= 20; },
    }));

    Alpine.data('productDesc', (cfg) => ({
        images: cfg.images,
        collapsed: cfg.collapsed,
        expanded: false,
        lightbox: false,
        lbIndex: 0,

        get hidden() { return Math.max(0, this.images.length - this.collapsed); },
        open(i) { this.lbIndex = i; this.lightbox = true; },
        close() { this.lightbox = false; },
        next() { this.lbIndex = (this.lbIndex + 1) % this.images.length; },
        prev() { this.lbIndex = (this.lbIndex - 1 + this.images.length) % this.images.length; },
    }));
});
JS, \yii\web\View::POS_END);
?>
