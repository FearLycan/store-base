<?php
/** @var yii\web\View $this */
/** @var app\models\Store $store */
/** @var yii\data\ActiveDataProvider $dataProvider */
/** @var array $current */
/** @var app\models\Category[] $categories */
/** @var bool $showcase */
/** @var app\models\StoreBanner[] $banners */
/** @var app\models\Product[] $bestsellers */
/** @var array<int, array{category: app\models\Category, image: string|null, count: int}> $topCategories */

use app\components\schema\builder\ListingPageSchemaBuilder;
use app\components\schema\JsonLdRenderer;
use app\components\Seo;
use yii\helpers\Html;
use yii\helpers\Url;

$canonical = Url::to(['/store/view', 'slug' => $store->slug], true);
Seo::apply($this, $store->name, 'Browse products sold by ' . $store->name . '.', $canonical);
$home = ['label' => 'Home', 'url' => Url::to(['/catalog/index'])];

$arrow = '<svg viewBox="0 0 16 16" fill="none" aria-hidden="true" class="h-3.5 w-3.5"><path d="M6 3.5 10.5 8 6 12.5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>';
?>
<?= JsonLdRenderer::render(ListingPageSchemaBuilder::build($dataProvider, [], $home, $store->name, $store->name)) ?>
<?= $this->render('//catalog/_partials/breadcrumbs', ['items' => [
    ['name' => 'Home', 'url' => Url::to(['/catalog/index'])],
    ['name' => $store->name, 'url' => null],
]]) ?>

<div class="mb-6 flex items-center gap-4">
    <?= $this->render('_logo', ['store' => $store, 'size' => 'lg']) ?>
    <div class="min-w-0">
        <h1 class="text-2xl font-bold leading-tight"><?= Html::encode($store->name) ?></h1>
        <p class="mt-1 text-sm text-gray-500"><?= number_format($dataProvider->totalCount) ?> product<?= $dataProvider->totalCount === 1 ? '' : 's' ?></p>
        <?= $this->render('_socials', ['store' => $store]) ?>
    </div>
</div>

<?php if ($showcase): ?>
    <?php if ($banners !== []): ?>
        <?= $this->render('_hero', ['banners' => $banners, 'store' => $store]) ?>
    <?php endif; ?>

    <?php if ($bestsellers !== []): ?>
        <section class="mb-12">
            <div class="mb-4 flex items-baseline justify-between gap-4">
                <h2 class="text-xl font-bold tracking-tight">Bestsellers</h2>
                <a href="<?= Url::to(['/store/view', 'slug' => $store->slug, 'sort' => 'popular']) ?>" class="rail-more">View all <?= $arrow ?></a>
            </div>
            <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-6">
                <?php foreach ($bestsellers as $product): ?>
                    <?= $this->render('//catalog/_partials/product-card', ['product' => $product]) ?>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>

    <?php if (count($topCategories) >= 2): ?>
        <?= $this->render('_top-categories', ['store' => $store, 'topCategories' => $topCategories]) ?>
    <?php endif; ?>

    <h2 class="mb-4 text-xl font-bold tracking-tight">All products</h2>
<?php endif; ?>

<?= $this->render('//catalog/_partials/filters', ['current' => $current, 'categories' => $categories, 'showCategory' => true, 'showStore' => false]) ?>
<?= $this->render('//catalog/_partials/active-filters', ['current' => $current, 'total' => $dataProvider->totalCount, 'categories' => $categories]) ?>
<?= $this->render('//catalog/_partials/_grid', ['dataProvider' => $dataProvider, 'empty' => 'No products from this store yet.']) ?>
