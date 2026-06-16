<?php
/** @var yii\web\View $this */
/** @var app\models\Category $category */
/** @var app\models\Category|null $parent */
/** @var app\models\Category[] $children */
/** @var yii\data\ActiveDataProvider $dataProvider */
/** @var array $current */
use app\components\Seo;
use app\components\schema\builder\ListingPageSchemaBuilder;
use app\components\schema\JsonLdRenderer;
use yii\helpers\Html;
use yii\helpers\Url;

$canonical = Url::to(['/catalog/category', 'slug' => $category->slug], true);
Seo::apply($this, $category->name, 'Browse ' . $category->name . ' products.', $canonical);
$home = ['label' => 'Home', 'url' => Url::to(['/catalog/index'])];

// Breadcrumb trail: Home > [parent] > current. `$schemaLinks` are the
// intermediate links for the JSON-LD breadcrumb; `$crumbs` feed the visible one.
$schemaLinks = [];
$crumbs = [['name' => 'Home', 'url' => Url::to(['/catalog/index'])]];
if ($parent !== null) {
    $parentUrl = Url::to(['/catalog/category', 'slug' => $parent->slug]);
    $schemaLinks[] = ['label' => $parent->name, 'url' => $parentUrl];
    $crumbs[] = ['name' => $parent->name, 'url' => $parentUrl];
}
$crumbs[] = ['name' => $category->name, 'url' => null];
?>
<?= JsonLdRenderer::render(ListingPageSchemaBuilder::build($dataProvider, $schemaLinks, $home, $category->name, $category->name)) ?>
<?= $this->render('_partials/breadcrumbs', ['items' => $crumbs]) ?>
<h1 class="mb-4 text-2xl font-bold"><?= Html::encode($category->name) ?></h1>
<?= $this->render('_partials/subcategories', ['category' => $category, 'parent' => $parent, 'children' => $children]) ?>
<?= $this->render('_partials/filters', ['current' => $current, 'showCategory' => false]) ?>
<?= $this->render('_partials/active-filters', ['current' => $current, 'total' => $dataProvider->totalCount]) ?>
<?= $this->render('_partials/_grid', ['dataProvider' => $dataProvider, 'empty' => 'No products here yet.']) ?>
