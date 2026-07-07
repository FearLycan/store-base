<?php
/** @var yii\web\View $this */
/** @var yii\data\ActiveDataProvider $dataProvider */
/** @var array $current */
/** @var app\models\Category[] $categories */
/** @var app\models\Store[] $stores */
/** @var app\models\Category[] $chips Leaf categories for the chip nav (same look as category pages). */
use app\components\Seo;
use app\components\schema\builder\ListingPageSchemaBuilder;
use app\components\schema\JsonLdRenderer;
use yii\helpers\Html;
use yii\helpers\Url;

// Prepare first so the paginator learns its totalCount: reading the page number
// before that (for the canonical/title below) would validate against a 0 count
// and clamp every page to 1. See Seo::apply.
$dataProvider->prepare();
Seo::apply($this, 'Catalog', 'Browse all products.', Url::to(['/catalog/all'], true), false, '', $dataProvider->getPagination());
$home = ['label' => 'Home', 'url' => Url::to(['/catalog/index'])];
?>
<?= JsonLdRenderer::render(ListingPageSchemaBuilder::build($dataProvider, [], $home, 'Catalog', 'Catalog')) ?>
<?= $this->render('_partials/breadcrumbs', ['items' => [['name' => 'Home', 'url' => Url::to(['/catalog/index'])], ['name' => 'Catalog', 'url' => null]]]) ?>
<h1 class="mb-4 text-2xl font-bold">Catalog</h1>
<?php if ($chips !== []): ?>
<nav class="mb-6 flex flex-wrap gap-2" aria-label="Categories">
    <a href="<?= Url::to(['/catalog/all']) ?>" class="subcat-chip is-active">All products</a>
    <?php foreach ($chips as $chip): ?>
        <a href="<?= Url::to(['/catalog/category', 'slug' => $chip->slug]) ?>" class="subcat-chip"><?= Html::encode($chip->name) ?></a>
    <?php endforeach; ?>
</nav>
<?php endif; ?>
<?= $this->render('_partials/filters', ['current' => $current, 'categories' => $categories, 'stores' => $stores, 'showCategory' => true, 'showStore' => true]) ?>
<?= $this->render('_partials/active-filters', ['current' => $current, 'total' => $dataProvider->totalCount, 'categories' => $categories, 'stores' => $stores]) ?>
<?= $this->render('_partials/_grid', ['dataProvider' => $dataProvider, 'empty' => 'No products yet.']) ?>
