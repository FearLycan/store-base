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
$home = ['label' => 'Home', 'url' => Url::to(['/catalog/index'])];
$crumbLinks = [];
if ($product->category !== null) {
    $crumbLinks[] = ['label' => $product->category->name, 'url' => Url::to(['/catalog/category', 'slug' => $product->category->slug])];
}
?>
<?= JsonLdRenderer::render(ProductPageSchemaBuilder::build($product, $canonical, $goUrl, $crumbLinks, $home)) ?>

<?= $this->render('//catalog/_partials/breadcrumbs', ['items' => array_values(array_filter([
    ['name' => 'Home', 'url' => Url::to(['/catalog/index'])],
    $product->category ? ['name' => $product->category->name, 'url' => Url::to(['/catalog/category', 'slug' => $product->category->slug])] : null,
    ['name' => (string)$product->title, 'url' => null],
]))]) ?>

<div class="grid gap-8 lg:grid-cols-2" x-data="{ main: <?= Html::encode(Json::encode($images[0])) ?> }">
    <div>
        <div class="aspect-square overflow-hidden rounded-xl border border-gray-200 bg-white">
            <img :src="main" alt="<?= Html::encode((string)$product->title) ?>" class="h-full w-full object-contain">
        </div>
        <?php if (count($images) > 1): ?>
        <div class="mt-3 flex gap-2 overflow-x-auto">
            <?php foreach ($images as $img): ?>
                <button type="button" @click="main = <?= Html::encode(Json::encode($img)) ?>" class="h-16 w-16 flex-none overflow-hidden rounded-lg border border-gray-200">
                    <img src="<?= Html::encode($img) ?>" alt="" loading="lazy" class="h-full w-full object-cover">
                </button>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

    <div>
        <h1 class="text-2xl font-bold"><?= Html::encode((string)$product->title) ?></h1>
        <div class="mt-2"><?= $this->render('//catalog/_partials/stars', ['value' => $product->rating_value, 'count' => $product->review_count]) ?></div>
        <div class="mt-4"><?= $this->render('//catalog/_partials/price', ['product' => $product]) ?></div>

        <?php if ($product->availability !== null && stripos((string)$product->availability, 'out') !== false): ?>
            <p class="mt-2 inline-block rounded bg-amber-100 px-2 py-1 text-sm text-amber-800">Currently unavailable</p>
        <?php endif; ?>

        <a href="<?= $goUrl ?>" target="_blank" rel="nofollow sponsored noopener" class="btn-accent mt-6 w-full">View on AliExpress →</a>
        <p class="mt-2 text-xs text-gray-400">Price/availability on AliExpress may differ.<?php if ($product->last_price_synced_at): ?> Updated <?= Yii::$app->formatter->asRelativeTime($product->last_price_synced_at) ?>.<?php endif; ?></p>

        <?php if ($product->variants): ?>
        <div class="mt-6">
            <h2 class="mb-2 font-semibold">Options</h2>
            <div class="flex flex-wrap gap-2">
                <?php foreach ($product->variants as $v): ?>
                    <span class="rounded border border-gray-300 px-2 py-1 text-sm" title="stock: <?= (int)$v->stock ?>"><?= Html::encode((string)($v->name ?: $v->external_sku_id)) ?></span>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($product->specs): ?>
        <div class="mt-6" x-data="{ open: false }">
            <button @click="open = !open" class="flex w-full items-center justify-between border-b py-2 font-semibold">Specifications <span x-text="open ? '−' : '+'"></span></button>
            <dl x-show="open" class="mt-2 grid grid-cols-1 gap-x-6 gap-y-1 text-sm sm:grid-cols-2">
                <?php foreach ($product->specs as $a): ?>
                    <div class="flex justify-between gap-4 border-b border-gray-100 py-1"><dt class="text-gray-500"><?= Html::encode($a->name) ?></dt><dd class="text-right"><?= Html::encode((string)$a->value) ?></dd></div>
                <?php endforeach; ?>
            </dl>
        </div>
        <?php endif; ?>
    </div>
</div>

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
