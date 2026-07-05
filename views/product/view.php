<?php
/** @var yii\web\View $this */
/** @var app\models\Product $product */

/** @var app\models\Product[] $related */

use app\components\schema\builder\ProductPageSchemaBuilder;
use app\components\schema\JsonLdRenderer;
use app\components\Seo;
use yii\helpers\Html;
use yii\helpers\Json;
use yii\helpers\Url;

$canonical = Url::to(['/product/view', 'slug' => $product->slug], true);
$descText = $product->description !== null ? trim(strip_tags((string) $product->description)) : $product->displayName;
Seo::apply($this, $product->displayName, $descText !== '' ? $descText : $product->displayName, $canonical, false, (string) $product->main_image);

$images = [];
foreach ($product->images as $img) {
    $images[] = $img->url;
}
if ($images === [] && $product->main_image) {
    $images[] = $product->main_image;
}
if ($images === []) {
    $images[] = '/img/placeholder.png';
}

// The raw "detail" HTML is a vendor blob: a tall stack of marketing graphics
// plus the odd spec line and cross-sell link spam. Restructure it into clean
// highlights + a de-duped image lookbook so we can present it on our terms.
$desc = \app\components\ProductDescription::parse($product->description, $images);
$descImages = $desc['images'];
$descHighlights = $desc['highlights'];
$descCollapsed = count($descImages) <= 9 ? count($descImages) : 6;

$goUrl = Url::to(['/go/index', 'id' => $product->id]);
// Full category chain (L1 > L2 > L3), not just the leaf, for the breadcrumb + JSON-LD.
$categoryTrail = [];
for ($c = $product->category; $c !== null; $c = $c->parent) {
    $categoryTrail[] = $c;
}
$categoryTrail = array_reverse($categoryTrail);
$crumbLinks = array_map(
    static fn($c): array => ['label' => $c->name, 'url' => Url::to(['/catalog/category', 'slug' => $c->slug])],
    $categoryTrail,
);

// --- Variant selector data ------------------------------------------------
// Group SKU variants by their option dimensions (e.g. "Metal Color", size). A
// value earns a swatch image only when every variant carrying it shares the
// same image — so colours get thumbnails while sizes stay as plain pills.
$groupNames = [];
$valueOrder = [];   // [name] => values in first-seen order
$valueImg = [];   // [name][value] => image|null (null once images conflict)
$valueSeen = [];   // [name][value] => true
$jsVariants = [];   // flat list handed to Alpine

foreach ($product->variants as $v) {
    $opts = is_array($v->options_json) ? $v->options_json : [];
    $clean = [];
    foreach ($opts as $name => $val) {
        $name = (string) $name;
        if ($name === '' || $name[0] === '_') {
            continue;
        } // skip _sku_attr et al.
        $val = trim((string) $val);
        if ($val === '') {
            continue;
        }
        $clean[$name] = $val;
        if (!in_array($name, $groupNames, true)) {
            $groupNames[] = $name;
            $valueOrder[$name] = [];
        }
        $img = ($v->image !== null && $v->image !== '') ? (string) $v->image : null;
        if (!isset($valueSeen[$name][$val])) {
            $valueSeen[$name][$val] = true;
            $valueOrder[$name][] = $val;
            $valueImg[$name][$val] = $img;
        } else if (($valueImg[$name][$val] ?? null) !== $img) {
            $valueImg[$name][$val] = null;
        }
    }
    $jsVariants[] = [
        'opts'   => (object) $clean,
        'price'  => $v->price !== null ? (int) $v->price : null,
        'oprice' => $v->original_price !== null ? (int) $v->original_price : null,
        'stock'  => $v->stock !== null ? (int) $v->stock : null,
        'image'  => ($v->image !== null && $v->image !== '') ? (string) $v->image : null,
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
        $matches = array_filter($vals, static fn($x): bool => preg_match($pattern, trim((string) $x)) === 1);
        if (count($matches) >= max(1, (int) ceil(count($vals) * 0.6))) {
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
        if ($img !== null) {
            $hasImage = true;
        }
        $values[] = ['value' => $val, 'image' => $img];
    }
    $groups[] = [
        'name'     => $name,
        'label'    => $prettyLabel($name, $valueOrder[$name]),
        'values'   => $values,
        'hasImage' => $hasImage,
    ];
}
// Image-bearing groups first (swatches), label-only groups after (pills).
usort($groups, static fn($a, $b): int => $b['hasImage'] <=> $a['hasImage']);

// Preselect the first in-stock variant (fall back to the first) so the page
// opens on a coherent image + price, matching the SSR-seeded values below.
$defaultVariant = null;
foreach ($product->variants as $v) {
    if ((int) $v->stock > 0) {
        $defaultVariant = $v;
        break;
    }
}
if ($defaultVariant === null && $product->variants) {
    $defaultVariant = $product->variants[0];
}

$defaultSelected = [];
if ($defaultVariant !== null && is_array($defaultVariant->options_json)) {
    foreach ($defaultVariant->options_json as $name => $val) {
        $name = (string) $name;
        if ($name === '' || $name[0] === '_') {
            continue;
        }
        $val = trim((string) $val);
        if ($val !== '') {
            $defaultSelected[$name] = $val;
        }
    }
}

$seedPrice = ($defaultVariant && $defaultVariant->price !== null) ? (int) $defaultVariant->price : $product->price;
$seedOprice = $defaultVariant ? ($defaultVariant->original_price !== null ? (int) $defaultVariant->original_price : null) : $product->original_price;
$seedCurrency = $product->currency_code ?: 'USD';
$seedDiscount = ($seedPrice && $seedOprice && $seedOprice > $seedPrice) ? (int) round((1 - $seedPrice / $seedOprice) * 100) : null;
$defaultImage = ($defaultVariant && $defaultVariant->image) ? (string) $defaultVariant->image : $images[0];

$pdpRec = [
    'slug'     => $product->slug,
    'url'      => Url::to(['/product/view', 'slug' => $product->slug]),
    'title'    => $product->displayName,
    'image'    => $product->main_image ?: ($images[0] ?? '/img/placeholder.png'),
    'price'    => $product->price !== null ? number_format($product->price / 100, 2) : null,
    'currency' => $seedCurrency,
];
$slugJson = Json::encode($product->slug);

$cfg = [
    'images'    => $images,
    'hasVideo'  => (bool) $product->video_url,
    'groups'    => array_map(static fn($g): array => [
        'name'   => $g['name'],
        'values' => array_map(static fn($x): array => ['value' => $x['value'], 'image' => $x['image']], $g['values']),
    ], $groups),
    'variants'  => $jsVariants,
    'base'      => ['price' => $product->price, 'oprice' => $product->original_price, 'currency' => $seedCurrency],
    'selected'  => (object) $defaultSelected,
    'mainImage' => $defaultImage,
];
?>
<?= JsonLdRenderer::render(ProductPageSchemaBuilder::build($product, $canonical, $goUrl, $crumbLinks, ['label' => 'Home', 'url' => Url::to(['/catalog/index'])])) ?>

<?= $this->render('//catalog/_partials/breadcrumbs', ['items' => array_merge(
    [['name' => 'Home', 'url' => Url::to(['/catalog/index'])]],
    array_map(
        static fn($c): array => ['name' => $c->name, 'url' => Url::to(['/catalog/category', 'slug' => $c->slug])],
        $categoryTrail,
    ),
    [['name' => $product->displayName, 'url' => null]],
)]) ?>
<div x-data x-init="$store.shop.pushRecent(<?= Html::encode(Json::encode($pdpRec)) ?>)" hidden></div>

<div class="grid gap-8 lg:grid-cols-2" x-data="productView(<?= Html::encode(Json::encode($cfg)) ?>)">
    <div class="min-w-0">
        <div class="group relative aspect-square select-none overflow-hidden rounded-xl border border-gray-200 bg-white">
            <?php if ($product->video_url): ?>
                <video x-show="showVideo" x-cloak controls preload="none" poster="<?= Html::encode($images[0]) ?>" class="h-full w-full object-contain">
                    <source src="<?= Html::encode((string) $product->video_url) ?>" type="video/mp4">
                </video>
            <?php endif; ?>
            <img x-show="!showVideo" :src="main" alt="<?= Html::encode($product->displayName) ?>" class="h-full w-full object-contain transition-opacity duration-150" loading="lazy">
            <?php if (count($images) > 1): ?>
                <button type="button" @click="prev()" x-show="!showVideo" x-cloak class="pdp-nav left-2" aria-label="Previous image">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-5 w-5">
                        <polyline points="15 18 9 12 15 6"></polyline>
                    </svg>
                </button>
                <button type="button" @click="next()" x-show="!showVideo" x-cloak class="pdp-nav right-2" aria-label="Next image">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-5 w-5">
                        <polyline points="9 18 15 12 9 6"></polyline>
                    </svg>
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

    <div class="min-w-0">
        <h1 class="text-2xl font-bold leading-snug"><?= Html::encode($product->displayName) ?></h1>

        <div class="mt-2 flex flex-wrap items-center gap-x-3 gap-y-1">
            <?= $this->render('//catalog/_partials/stars', ['value' => $product->rating_value, 'count' => $product->review_count]) ?>
            <?php if ((int) $product->orders_count > 0): ?>
                <span class="text-sm text-gray-500"><?= number_format((int) $product->orders_count) ?> sold</span>
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

        <?php if (($priceDrop = $product->priceDropAmount()) !== null): ?>
            <p class="mt-2 inline-flex items-center gap-1.5 rounded-md bg-emerald-50 px-2 py-1 text-sm font-semibold text-emerald-700">
                <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-3.5 w-3.5" aria-hidden="true">
                    <path d="M8 3v10M4 9l4 4 4-4"/>
                </svg>
                Price dropped <?= Html::encode($product->currency_code ?: 'USD') ?> <?= Html::encode(number_format($priceDrop / 100, 2)) ?>
            </p>
        <?php endif; ?>

        <?php if ($product->availability !== null && stripos((string) $product->availability, 'out') !== false): ?>
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
                                    title="<?= Html::encode((string) $val['value']) ?>">
                                <?php if ($val['image']): ?>
                                    <img src="<?= Html::encode($val['image']) ?>" alt="<?= Html::encode((string) $val['value']) ?>" loading="lazy">
                                <?php else: ?>
                                    <span><?= Html::encode((string) $val['value']) ?></span>
                                <?php endif; ?>
                            </button>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="flex flex-wrap gap-2">
                        <?php foreach ($g['values'] as $vi => $val): ?>
                            <button type="button" @click="pick(<?= $gi ?>, <?= $vi ?>)"
                                    class="variant-pill" :class="{ 'is-active': isSel(<?= $gi ?>, <?= $vi ?>), 'is-out': !avail(<?= $gi ?>, <?= $vi ?>) }">
                                <?= Html::encode((string) $val['value']) ?>
                            </button>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>

        <a href="<?= $goUrl ?>" target="_blank" rel="nofollow sponsored noopener" x-ref="buyCta" class="btn-accent mt-6 w-full">View on AliExpress →</a>
        <button type="button" class="pdp-fav" :class="{ 'is-on': $store.shop.isFav(<?= Html::encode($slugJson) ?>) }"
                @click="$store.shop.toggleFav(<?= Html::encode(Json::encode($pdpRec)) ?>)" :aria-pressed="$store.shop.isFav(<?= Html::encode($slugJson) ?>)">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.6l-1-1a5.5 5.5 0 0 0-7.8 7.8l1 1L12 21l7.8-7.6 1-1a5.5 5.5 0 0 0 0-7.8z"/>
            </svg>
            <span x-text="$store.shop.isFav(<?= Html::encode($slugJson) ?>) ? 'In wishlist' : 'Add to wishlist'">Add to wishlist</span>
        </button>
        <p class="mt-2 text-xs text-gray-400">Price/availability on AliExpress may differ.<?php if ($product->last_price_synced_at): ?> Updated <?= Yii::$app->formatter->asRelativeTime($product->last_price_synced_at) ?>.<?php endif; ?></p>

        <?php if ($product->store !== null && $product->store->slug !== null): ?>
            <a href="<?= Url::to(['/store/view', 'slug' => $product->store->slug]) ?>" class="mt-4 flex items-center gap-3 rounded-xl border border-gray-200 bg-white p-3 transition-colors hover:border-[color:var(--accent)]">
                <?= $this->render('//store/_logo', ['store' => $product->store, 'size' => 'sm']) ?>
                <span class="min-w-0 flex-1">
                    <span class="block text-xs text-gray-400">Sold by</span>
                    <span class="block truncate font-semibold text-gray-900"><?= Html::encode($product->store->name) ?></span>
                </span>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-5 w-5 flex-none text-gray-400" aria-hidden="true">
                    <polyline points="9 18 15 12 9 6"/>
                </svg>
            </a>
        <?php endif; ?>
    </div>

    <!-- Sticky mobile buy bar: mirrors the selected-variant price + affiliate CTA.
         Mobile only (sm:hidden); revealed by `is-on` once the in-page CTA scrolls off. -->
    <div class="pdp-buybar sm:hidden" :class="{ 'is-on': showBar }" :aria-hidden="!showBar">
        <div class="mx-auto flex max-w-7xl items-center justify-between gap-3 px-4">
            <div class="min-w-0">
                <div class="flex items-baseline gap-2">
                    <span x-show="priceText" class="text-lg font-bold tabular-nums text-gray-900"><span x-text="priceText"></span> <span class="text-xs font-semibold text-gray-500"><?= Html::encode($seedCurrency) ?></span></span>
                    <span x-show="!priceText" class="text-sm font-semibold text-[color:var(--accent)]">Check price</span>
                    <span x-show="opriceText" x-cloak class="text-xs text-gray-400 line-through tabular-nums" x-text="opriceText"></span>
                    <span x-show="discount" x-cloak class="pdp-discount">−<span x-text="discount"></span>%</span>
                </div>
            </div>
            <a href="<?= $goUrl ?>" target="_blank" rel="nofollow sponsored noopener" class="btn-accent flex-none whitespace-nowrap px-4 py-2.5 text-sm">View on AliExpress →</a>
        </div>
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
                        <dd class="text-right font-medium text-gray-800"><?= Html::encode((string) $a->value) ?></dd>
                    </div>
                <?php endforeach; ?>
            </dl>
        </div>
        <?php if (count($product->specs) > 8): ?>
            <button @click="open = !open" class="desc-more">
                <span x-text="open ? 'Show less' : 'Show all specifications'"></span>
                <span x-show="!open" x-cloak class="tabular-nums text-gray-400">(<?= count($product->specs) ?>)</span>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4 transition-transform duration-200" :class="{ '-rotate-180': open }">
                    <polyline points="6 9 12 15 18 9"/>
                </svg>
            </button>
        <?php endif; ?>
    </section>
<?php endif; ?>

<?= $this->render('//catalog/_partials/price-chart', ['product' => $product]) ?>

<?php if ($descHighlights || $descImages): ?>
    <section class="mt-10" x-data="productDesc(<?= Html::encode(Json::encode(['images' => $descImages, 'collapsed' => $descCollapsed])) ?>)">
        <h2 class="mb-4 text-xl font-bold">Product details</h2>

        <?php if ($descHighlights): ?>
            <div class="desc-highlights">
                <div class="mb-3 flex items-center gap-2 text-sm font-semibold text-gray-900">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4 text-[color:var(--accent)]">
                        <path d="M12 2 9.2 8.6 2 9.2l5.5 4.7L5.8 21 12 17.3 18.2 21l-1.7-7.1L22 9.2l-7.2-.6z"/>
                    </svg>
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
                            <?php if ($im['w'] && $im['h']): ?>style="aspect-ratio:<?= (int) $im['w'] ?>/<?= (int) $im['h'] ?>"<?php endif; ?>>
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
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4">
                        <polyline points="6 9 12 15 18 9"/>
                    </svg>
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
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-5 w-5">
                    <line x1="18" y1="6" x2="6" y2="18"/>
                    <line x1="6" y1="6" x2="18" y2="18"/>
                </svg>
            </button>
            <button type="button" @click.stop="prev()" class="lb-btn absolute left-3 top-1/2 -translate-y-1/2" aria-label="Previous">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-6 w-6">
                    <polyline points="15 18 9 12 15 6"/>
                </svg>
            </button>
            <img :src="images[lbIndex]?.url" alt="" class="max-h-[88vh] max-w-full rounded-lg object-contain shadow-2xl" @click.stop>
            <button type="button" @click.stop="next()" class="lb-btn absolute right-3 top-1/2 -translate-y-1/2" aria-label="Next">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-6 w-6">
                    <polyline points="9 18 15 12 9 6"/>
                </svg>
            </button>
            <div class="absolute bottom-4 left-1/2 -translate-x-1/2 rounded-full bg-white/10 px-3 py-1 text-sm font-medium tabular-nums text-white/90"><span x-text="lbIndex + 1"></span> / <span x-text="images.length"></span></div>
        </div>
    </section>
<?php endif; ?>

<?php
// --- Reviews --------------------------------------------------------------
// Render review text server-side (SEO) and let Alpine handle filtering by
// keyword/photos plus a shared lightbox. "Impressions" are the keyword tallies
// AliExpress derives from the reviews (stored on product.review_impressions).
// Existing data still holds rating-only blanks and AliExpress's shifting-id
// duplicates, so filter + dedupe here to keep the section clean even before a
// backfill. (Import now prevents both — see ProductReview::syncByProduct.)
$revs = [];
$seenReviews = [];
foreach ($product->reviews as $r) {
    $content = trim((string) $r->content);
    // Rating-only reviews (no text AND no photo) are noise. Text short-circuits the
    // image lookup, so only blank-text rows ever touch the images relation.
    if ($content === '' && count($r->images) === 0) {
        continue;
    }
    $fp = sha1(trim((string) $r->author_name) . '|' . (int) $r->reviewed_at . '|' . $content);
    if (isset($seenReviews[$fp])) {
        continue;
    }
    $seenReviews[$fp] = true;
    $revs[] = $r;
    if (count($revs) >= 20) {
        break;
    }
}
?>
<?php if ($revs): ?>
    <?php
    $impressions = is_array($product->review_impressions) ? $product->review_impressions : [];

    $starRow = static function (float $v, string $cls = 'text-sm'): string {
        $pct = max(0.0, min(100.0, $v / 5 * 100));
        return '<span class="relative inline-block whitespace-nowrap leading-none ' . $cls . '" aria-hidden="true">'
            . '<span class="text-gray-200">★★★★★</span>'
            . '<span class="absolute inset-0 overflow-hidden text-amber-400" style="width:' . round($pct, 1) . '%">★★★★★</span>'
            . '</span>';
    };

// 2-letter ISO country code -> flag emoji (regional indicator pair).
    $flagOf = static function (?string $cc): string {
        if ($cc === null) {
            return '';
        }
        $cc = strtoupper(trim($cc));
        if (preg_match('~^[A-Z]{2}$~', $cc) !== 1) {
            return '';
        }
        return mb_convert_encoding('&#' . (127397 + ord($cc[0])) . ';', 'UTF-8', 'HTML-ENTITIES')
            . mb_convert_encoding('&#' . (127397 + ord($cc[1])) . ';', 'UTF-8', 'HTML-ENTITIES');
    };
    $avatarColors = ['#fb7185', '#f59e0b', '#10b981', '#60a5fa', '#a78bfa', '#f472b6', '#2dd4bf', '#fb923c'];

    $dist = [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0];
    $photoStrip = [];   // [url, rating] for the top customer-photos strip
    $allImages = [];   // flat url list backing the lightbox
    $captions = [];   // per-image caption (author/rating/text of the source review)
    $meta = [];   // per-card filter metadata for Alpine
    $cards = [];   // prepared per-review render data
    $photoCount = 0;
    foreach ($revs as $r) {
        $rating = (float) $r->rating_value;
        $bucket = (int) round($rating);
        if ($bucket >= 1 && $bucket <= 5) {
            $dist[$bucket]++;
        }

        $name = trim((string) ($r->author_name ?: 'Anonymous'));
        $initial = preg_match('~[\p{L}\p{N}]~u', $name, $m) === 1 ? mb_strtoupper($m[0]) : '?';
        $content = trim((string) $r->content);
        $flag = $flagOf($r->reviewer_country);
        $date = $r->reviewed_at ? Yii::$app->formatter->asDate($r->reviewed_at, 'medium') : '';

        $imgIdx = [];
        foreach ($r->images as $ri) {
            $url = trim((string) $ri->url);
            if ($url === '') {
                continue;
            } // blank rows must not count as a photo
            $gi = count($allImages);
            $imgIdx[] = $gi;
            $photoStrip[] = ['url' => $url, 'rating' => $rating, 'i' => $gi];
            $allImages[] = $url;
            $captions[$gi] = ['n' => $name, 'f' => $flag, 'r' => $rating, 'd' => $date, 'c' => $content];
        }
        if ($imgIdx !== []) {
            $photoCount++;
        }

        $cards[] = [
            'name'    => $name,
            'initial' => $initial,
            'color'   => $avatarColors[abs(crc32($name)) % count($avatarColors)],
            'flag'    => $flag,
            'rating'  => $rating,
            'date'    => $date,
            'content' => $content,
            'images'  => $imgIdx,
        ];
        $meta[] = ['p' => $imgIdx !== [], 't' => mb_strtolower($content), 'r' => $rating, 'd' => (int) $r->reviewed_at];
    }
    $revTotal = count($cards);

    // Prefer AE's real full-corpus aggregates over the tiny stored sample, so "all"
    // is never smaller than a filter and the photo strip matches "with photos".
    // Falls back to the sample when a product hasn't been backfilled yet.
    $realTotal = (int) ($product->review_total ?? 0) ?: $revTotal;

    $rd = is_array($product->review_rating_dist) ? $product->review_rating_dist : [];
    $realDist = [];
    $hasRealDist = false;
    foreach ([5, 4, 3, 2, 1] as $s) {
        $realDist[$s] = (int) ($rd[$s] ?? $rd[(string) $s] ?? 0);
        if ($realDist[$s] > 0) {
            $hasRealDist = true;
        }
    }
    if (!$hasRealDist) {
        $realDist = $dist; // sample fallback
    }

    // Independent photo strip (real AE photos when present, else the sample). Its
    // lightbox uses a separate image set from the card list, since the list gets
    // replaced when a filter loads.
    $stripPhotos = [];   // [{u,r}] backing the strip + its own lightbox
    foreach (is_array($product->review_photos) ? $product->review_photos : [] as $ph) {
        $u = trim((string) ($ph['u'] ?? ''));
        if ($u !== '') {
            $stripPhotos[] = ['u' => $u, 'r' => (float) ($ph['r'] ?? 0)];
        }
    }
    if ($stripPhotos === []) {
        foreach ($photoStrip as $ph) {   // sample fallback
            $stripPhotos[] = ['u' => $ph['url'], 'r' => (float) $ph['rating']];
        }
    }
    $stripCount = (int) ($product->review_image_count ?? 0) ?: count($stripPhotos);

    $reviewsCfg = [
        'productId'     => (int) $product->id,
        'images'        => $allImages,
        'captions'      => $captions,
        'total'         => $realTotal,
        'hasMore'       => $realTotal > $revTotal, // AE has more than the stored baseline
        'stripImages'   => array_map(static fn($p) => $p['u'], $stripPhotos),
        'stripCaptions' => array_map(static fn($p) => ['r' => $p['r']], $stripPhotos),
        'ssrHtml'       => $this->render('_review-cards', ['cards' => array_map([\app\services\ReviewCardMapper::class, 'fromModel'], $revs), 'imgBase' => 0]),
    ];
    ?>
    <section class="mt-10" x-data="productReviews(<?= Html::encode(Json::encode($reviewsCfg)) ?>)">
        <h2 class="mb-4 text-xl font-bold">Customer reviews</h2>

        <!-- Summary: average + verified note + rating distribution -->
        <div class="rev-summary">
            <div class="flex flex-none items-center gap-4 sm:flex-col sm:items-start sm:gap-1">
                <div class="text-5xl font-bold leading-none tabular-nums text-gray-900"><?= number_format((float) $product->rating_value, 1) ?></div>
                <div>
                    <?= $starRow((float) $product->rating_value, 'text-lg') ?>
                    <div class="mt-1 text-sm text-gray-500"><?= number_format($realTotal) ?> review<?= $realTotal === 1 ? '' : 's' ?></div>
                </div>
            </div>
            <div class="hidden w-px self-stretch bg-gray-100 sm:block"></div>
            <div class="min-w-0 flex-1">
                <?php for ($s = 5; $s >= 1; $s--): $pct = $realTotal ? round($realDist[$s] / $realTotal * 100) : 0; ?>
                    <button type="button" class="rev-dist-row" :class="{ 'is-active': isActive('<?= $s ?>') }"
                            @click="setFilter('<?= $s ?>')"<?= $realDist[$s] === 0 ? ' disabled' : '' ?>
                            aria-label="Show <?= $realDist[$s] ?> <?= $s ?>-star review<?= $realDist[$s] === 1 ? '' : 's' ?>">
                        <span class="w-3 flex-none text-right tabular-nums text-gray-500"><?= $s ?></span>
                        <span class="text-amber-400">★</span>
                        <span class="rev-bar-track"><span class="rev-bar-fill" style="width:<?= $pct ?>%"></span></span>
                        <span class="w-6 flex-none text-right tabular-nums text-gray-400"><?= $realDist[$s] ?></span>
                    </button>
                <?php endfor; ?>
                <div class="mt-2 inline-flex items-center gap-1.5 text-xs font-medium text-emerald-600">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" class="h-3.5 w-3.5">
                        <path d="M20 6 9 17l-5-5"/>
                    </svg>
                    All from verified purchases
                </div>
            </div>
        </div>

        <?php if ($stripPhotos): ?>
            <!-- Customer photos. Real AE photos + count; its own lightbox set (openStrip). -->
            <div class="mt-6">
                <div class="mb-2.5 text-sm font-semibold text-gray-900">Photos from reviews <span class="font-normal tabular-nums text-gray-400">(<?= number_format($stripCount) ?>)</span></div>
                <div class="flex gap-2.5 overflow-x-auto pb-1.5">
                    <?php foreach (array_slice($stripPhotos, 0, 14) as $k => $ph): ?>
                        <button type="button" @click="openStrip(<?= (int) $k ?>)" class="rev-photo" aria-label="Open review photo">
                            <img src="<?= Html::encode($ph['u']) ?>" alt="" loading="lazy">
                            <span class="rev-photo-badge"><span class="text-amber-300">★</span><?= number_format($ph['r'], 1) ?></span>
                            <?php if ($k === 13 && $stripCount > 14): ?>
                                <span class="absolute inset-0 flex items-center justify-center bg-black/55 text-sm font-semibold text-white">+<?= number_format($stripCount - 14) ?></span>
                            <?php endif; ?>
                        </button>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <?php $imgChipCount = (int) ($product->review_image_count ?? 0); ?>
        <?php if ($impressions || $imgChipCount): ?>
            <!-- Filters: all / with photos / impression keywords. Each emits an AE filter token;
                 Alpine fetches /product/<id>/reviews?filter=<token> and swaps the list in. -->
            <div class="mt-6 flex flex-wrap gap-2">
                <button type="button" class="rev-chip" :class="{ 'is-active': isActive('all') }" @click="setFilter('all')">All</button>
                <?php if ($imgChipCount): ?>
                    <button type="button" class="rev-chip" :class="{ 'is-active': isActive('image') }" @click="setFilter('image')">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-3.5 w-3.5">
                            <rect x="3" y="3" width="18" height="18" rx="2"/>
                            <circle cx="9" cy="9" r="2"/>
                            <path d="m21 15-3.1-3.1a2 2 0 0 0-2.8 0L6 21"/>
                        </svg>
                        With photos <span class="rev-chip-num"><?= $imgChipCount ?></span>
                    </button>
                <?php endif; ?>
                <?php foreach ($impressions as $imp): $label = trim((string) ($imp['label'] ?? '')); $impId = trim((string) ($imp['id'] ?? ''));
                    if ($label === '' || $impId === '') {
                        continue;
                    } ?>
                    <button type="button" class="rev-chip" :class="{ 'is-active': isActive('impression:<?= Html::encode($impId) ?>') }" @click="setFilter('impression:<?= Html::encode($impId) ?>')">
                        <?= Html::encode($label) ?><?php if ((int) ($imp['num'] ?? 0) > 0): ?> <span class="rev-chip-num"><?= (int) $imp['num'] ?></span><?php endif; ?>
                    </button>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- Review count. (Sort control removed: AE's review API ignores the `sort`
             param — every token returns identical order — so a working dropdown isn't
             possible here. See plan Task 8.) -->
        <div class="mt-6 flex items-center gap-3">
            <span class="text-sm text-gray-500"><span x-text="total"></span> review<span x-show="total !== 1">s</span></span>
        </div>

        <!-- Skeleton placeholders shown only while a filter fetch REPLACES the list. -->
        <div x-show="skeleton" x-cloak aria-hidden="true" class="mt-3.5 flex flex-col gap-3.5">
            <?php for ($sk = 0; $sk < 3; $sk++): ?>
                <div class="rev-skeleton">
                    <div class="flex items-start gap-3">
                        <div class="rev-sk h-9 w-9 flex-none rounded-full"></div>
                        <div class="flex-1 space-y-2 pt-1">
                            <div class="rev-sk h-3 w-28"></div>
                            <div class="rev-sk h-2.5 w-20"></div>
                        </div>
                    </div>
                    <div class="mt-3.5 space-y-2">
                        <div class="rev-sk h-3 w-full"></div>
                        <div class="rev-sk h-3 w-11/12"></div>
                        <div class="rev-sk h-3 w-2/3"></div>
                    </div>
                </div>
            <?php endfor; ?>
        </div>

        <!-- Review list. SSR baseline (stored reviews via _review-cards) is the instant/SEO
             paint; Alpine replaces $refs.list innerHTML when a filter chip is clicked. -->
        <div class="mt-3.5 flex flex-col gap-3.5" x-ref="list" data-ssr="1"
             x-show="!skeleton"
             x-transition:enter="transition-opacity duration-300 ease-out"
             x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100">
            <?= $this->render('_review-cards', ['cards' => array_map([\app\services\ReviewCardMapper::class, 'fromModel'], $revs), 'imgBase' => 0]) ?>
        </div>
        <p x-show="empty" x-cloak class="mt-3.5 rounded-xl border border-dashed border-gray-200 py-8 text-center text-sm text-gray-500">No reviews match this filter.</p>
        <p x-show="failed" x-cloak class="mt-3.5 rounded-xl border border-dashed border-amber-200 bg-amber-50 py-4 text-center text-sm text-amber-700">Couldn’t load filtered reviews right now — showing recent ones.</p>

        <button type="button" @click="loadMore()" x-show="hasMore" x-cloak class="desc-more mt-4">
            <svg x-show="loading" viewBox="0 0 24 24" fill="none" class="h-4 w-4 animate-spin">
                <circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="2.5" stroke-opacity="0.25"/>
                <path d="M21 12a9 9 0 0 0-9-9" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"/>
            </svg>
            <span x-text="loading ? 'Loading…' : 'Show more reviews'"></span>
        </button>

        <!-- Lightbox -->
        <div x-show="lightbox" x-cloak @keydown.escape.window="close()"
             @keydown.arrow-right.window="lightbox && next()" @keydown.arrow-left.window="lightbox && prev()"
             x-effect="document.body.style.overflow = lightbox ? 'hidden' : ''"
             x-transition:enter="transition duration-200 ease-out" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
             x-transition:leave="transition duration-150 ease-in" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
             class="fixed inset-0 z-50 flex items-center justify-center bg-black/85 p-4 backdrop-blur-sm" @click.self="close()">
            <button type="button" @click="close()" class="lb-btn absolute right-3 top-3" aria-label="Close">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-5 w-5">
                    <line x1="18" y1="6" x2="6" y2="18"/>
                    <line x1="6" y1="6" x2="18" y2="18"/>
                </svg>
            </button>
            <button type="button" @click.stop="prev()" x-show="lbImages.length > 1" class="lb-btn absolute left-3 top-1/2 -translate-y-1/2" aria-label="Previous">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-6 w-6">
                    <polyline points="15 18 9 12 15 6"/>
                </svg>
            </button>
            <img :src="lbImages[lbIndex]" alt="" class="max-h-[78vh] max-w-full rounded-lg object-contain shadow-2xl" @click.stop>
            <button type="button" @click.stop="next()" x-show="lbImages.length > 1" class="lb-btn absolute right-3 top-1/2 -translate-y-1/2" aria-label="Next">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-6 w-6">
                    <polyline points="9 18 15 12 9 6"/>
                </svg>
            </button>
            <div x-show="lbImages.length > 1" class="absolute left-1/2 top-3 -translate-x-1/2 rounded-full bg-white/10 px-3 py-1 text-sm font-medium tabular-nums text-white/90"><span x-text="lbIndex + 1"></span> / <span x-text="lbImages.length"></span></div>
            <!-- Caption: the review the current photo belongs to -->
            <div x-show="lbCaptions[lbIndex] && (lbCaptions[lbIndex].c || lbCaptions[lbIndex].n)" x-cloak
                 class="pointer-events-none absolute inset-x-0 bottom-0 bg-gradient-to-t from-black/85 via-black/55 to-transparent px-4 pb-5 pt-16">
                <div class="mx-auto max-w-2xl text-white">
                    <div class="flex flex-wrap items-center gap-x-2 gap-y-1">
                        <span class="text-sm font-semibold" x-text="lbCaptions[lbIndex]?.n"></span>
                        <span x-show="lbCaptions[lbIndex]?.f" class="text-sm leading-none" x-text="lbCaptions[lbIndex]?.f"></span>
                        <span class="inline-flex items-center gap-0.5 text-xs text-amber-400"><span>★</span><span class="tabular-nums" x-text="lbCaptions[lbIndex]?.r?.toFixed(1)"></span></span>
                        <span x-show="lbCaptions[lbIndex]?.d" class="text-xs text-white/55" x-text="lbCaptions[lbIndex]?.d"></span>
                    </div>
                    <p x-show="lbCaptions[lbIndex]?.c" class="mt-1 max-h-24 overflow-y-auto text-sm leading-relaxed text-white/90" x-text="lbCaptions[lbIndex]?.c"></p>
                </div>
            </div>
        </div>
    </section>
<?php endif; ?>

<?php if ($related): ?>
    <section class="mt-10">
        <h2 class="mb-4 text-xl font-bold">You may also like</h2>
        <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4">
            <?php foreach ($related as $p): ?>
                <?= $this->render('//catalog/_partials/product-card', ['product' => $p]) ?>
            <?php endforeach; ?>
        </div>
    </section>
<?php endif; ?>
<?= $this->render('//catalog/_partials/recent-strip', ['excludeSlug' => $product->slug]) ?>

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
        showBar: false,

        // Reveal the sticky mobile buy bar once the in-page CTA scrolls out of view.
        init() {
            const cta = this.$refs.buyCta;
            if (!cta || typeof IntersectionObserver === 'undefined') { return; }
            const io = new IntersectionObserver((entries) => { this.showBar = !entries[0].isIntersecting; });
            io.observe(cta);
        },

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

    // Fetch-driven: chips/star-rows/pagination hit GET /product/<id>/reviews (which proxies
    // AliExpress's own review API + caches), and swap the rendered card HTML into $refs.list.
    // The SSR baseline stays in the DOM until the first filter/paging action.
    Alpine.data('productReviews', (cfg) => ({
        productId: cfg.productId,
        images: cfg.images,      // seeded from the SSR baseline; replaced on fetch
        captions: cfg.captions,
        total: cfg.total,
        filter: 'all',
        page: 1,
        totalPage: 1,
        hasMore: cfg.hasMore,
        loading: false,
        skeleton: false,   // show placeholder cards while a filter REPLACES the list
        failed: false,
        empty: false,
        lightbox: false,
        lbIndex: 0,
        lbImages: [],      // lightbox reads these; set at open time so the card list
        lbCaptions: {},    // and the (independent) photo strip each drive their own set

        isActive(token) { return this.filter === String(token); },

        // Clicking the active chip again toggles back to the "all" baseline.
        async setFilter(token) {
            token = String(token);
            const next = this.filter === token ? 'all' : token;
            this.filter = next;
            this.page = 1;
            if (next === 'all') { this.restoreBaseline(); return; }
            await this.fetch(false, true); // skeleton: the whole list is being replaced
        },

        async loadMore() {
            if (this.loading || !this.hasMore) { return; }
            if (this.filter === 'all' && this.$refs.list.dataset.ssr === '1') {
                // First paging away from the SSR baseline: fetch AE page 1 of "all".
                // No skeleton — baseline cards stay visible until the swap, so it reads
                // as a smooth in-place refresh rather than a flash of placeholders.
                this.page = 1; await this.fetch(false); return;
            }
            this.page += 1;
            await this.fetch(true);
        },

        restoreBaseline() {
            this.$refs.list.innerHTML = cfg.ssrHtml;
            this.$refs.list.dataset.ssr = '1';
            this.images = cfg.images;
            this.captions = cfg.captions;
            this.total = cfg.total;
            this.hasMore = cfg.hasMore;
            this.failed = false;
            this.empty = false;
            this.bindThumbs();
        },

        async fetch(append, skeleton = false) {
            this.loading = true;
            this.skeleton = skeleton && !append;
            this.failed = false;
            try {
                const imgBase = append ? this.images.length : 0;
                const url = `/product/${this.productId}/reviews?filter=${encodeURIComponent(this.filter)}&page=${this.page}&imgBase=${imgBase}`;
                const r = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
                const d = await r.json();
                if (!d.ok) { this.failed = true; return; }
                if (append) {
                    this.$refs.list.insertAdjacentHTML('beforeend', d.html);
                    this.images = this.images.concat(d.images);
                    Object.assign(this.captions, d.captions);
                } else {
                    this.$refs.list.innerHTML = d.html;
                    this.$refs.list.dataset.ssr = '0';
                    this.images = d.images;
                    this.captions = d.captions;
                }
                this.total = d.total;
                this.totalPage = d.totalPage;
                this.hasMore = d.hasMore;
                this.empty = !append && d.html.trim() === '';
                this.bindThumbs();
            } catch (e) {
                this.failed = true;
            } finally {
                this.loading = false;
                this.skeleton = false;
            }
        },

        // Injected [data-lb] thumbs can't carry an inline Alpine @click, so wire them up
        // via event delegation after every innerHTML/insertAdjacentHTML swap (and on init).
        bindThumbs() {
            this.$refs.list.querySelectorAll('[data-lb]').forEach((el) => {
                el.onclick = () => this.open(parseInt(el.dataset.lb, 10));
            });
        },
        init() { this.bindThumbs(); },

        // Card thumb → lightbox over the current list's images. Strip photo → its own set.
        open(i) { this.lbImages = this.images; this.lbCaptions = this.captions; this.lbIndex = i; this.lightbox = true; },
        openStrip(i) { this.lbImages = cfg.stripImages || []; this.lbCaptions = cfg.stripCaptions || {}; this.lbIndex = i; this.lightbox = true; },
        close() { this.lightbox = false; },
        next() { this.lbIndex = (this.lbIndex + 1) % this.lbImages.length; },
        prev() { this.lbIndex = (this.lbIndex - 1 + this.lbImages.length) % this.lbImages.length; },
    }));
});
JS, \yii\web\View::POS_END);
?>
