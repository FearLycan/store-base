<?php
/** @var yii\web\View $this */
/** @var yii\data\ActiveDataProvider $dataProvider */
/** @var array $current */
/** @var app\models\Category[] $categories */
use app\components\Seo;
use app\components\schema\builder\ListingPageSchemaBuilder;
use app\components\schema\JsonLdRenderer;
use yii\helpers\Url;

Seo::apply($this, 'Catalog', 'Browse all products.', Url::to(['/catalog/all'], true));
$home = ['label' => 'Home', 'url' => Url::to(['/catalog/index'])];
?>
<?= JsonLdRenderer::render(ListingPageSchemaBuilder::build($dataProvider, [], $home, 'Catalog', 'Catalog')) ?>
<?= $this->render('_partials/breadcrumbs', ['items' => [['name' => 'Home', 'url' => Url::to(['/catalog/index'])], ['name' => 'Catalog', 'url' => null]]]) ?>
<h1 class="mb-4 text-2xl font-bold">Catalog</h1>
<?= $this->render('_partials/filters', ['current' => $current, 'categories' => $categories, 'showCategory' => true]) ?>
<?= $this->render('_partials/active-filters', ['current' => $current, 'total' => $dataProvider->totalCount, 'categories' => $categories]) ?>
<?= $this->render('_partials/_grid', ['dataProvider' => $dataProvider, 'empty' => 'No products yet.']) ?>
