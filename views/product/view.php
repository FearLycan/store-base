<?php
/** @var yii\web\View $this */
/** @var app\models\Product $product */
/** @var app\models\Product[] $related */
use app\components\Seo;
use app\components\schema\builder\ProductPageSchemaBuilder;
use app\components\schema\JsonLdRenderer;
use yii\helpers\Html;
use yii\helpers\HtmlPurifier;
use yii\helpers\Json;
use yii\helpers\Url;

$canonical = Url::to(['/product/view', 'slug' => $product->slug], true);
$descText = $product->description !== null ? trim(strip_tags((string)$product->description)) : (string)$product->title;
Seo::apply($this, (string)$product->title, $descText !== '' ? $descText : (string)$product->title, $canonical, false, (string)$product->main_image);

$images = [];
foreach ($product->images as $img) { $images[] = $img->url; }
if ($images === [] && $product->main_image) { $images[] = $product->main_image; }
if ($images === []) { $images[] = '/img/placeholder.png'; }

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
    ['name' => (string)$product->title, 'url' => null],
]))]) ?>

<div class="grid gap-8 lg:grid-cols-2" x-data="productView(<?= Html::encode(Json::encode($cfg)) ?>)">
    <div>
        <div class="aspect-square select-none overflow-hidden rounded-xl border border-gray-200 bg-white">
            <?php if ($product->video_url): ?>
            <video x-show="showVideo" x-cloak controls preload="none" poster="<?= Html::encode($images[0]) ?>" class="h-full w-full object-contain">
                <source src="<?= Html::encode((string)$product->video_url) ?>" type="video/mp4">
            </video>
            <?php endif; ?>
            <img x-show="!showVideo" :src="main" alt="<?= Html::encode((string)$product->title) ?>" class="h-full w-full object-contain transition-opacity duration-150" loading="lazy">
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
        <h1 class="text-2xl font-bold leading-snug"><?= Html::encode((string)$product->title) ?></h1>

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

<?php if ($product->description !== null && trim((string)$product->description) !== ''): ?>
<section class="mt-10">
    <h2 class="mb-3 text-xl font-bold">Description</h2>
    <div class="prose max-w-none rounded-xl border border-gray-200 bg-white p-4"><?= HtmlPurifier::process((string)$product->description) ?></div>
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
});
JS, \yii\web\View::POS_END);
?>
