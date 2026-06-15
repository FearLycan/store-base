<?php
/** @var yii\web\View $this */
/** @var app\models\Category $category */
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
?>
<?= JsonLdRenderer::render(ListingPageSchemaBuilder::build($dataProvider, [], $home, $category->name, $category->name)) ?>
<?= $this->render('_partials/breadcrumbs', ['items' => [['name' => 'Home', 'url' => Url::to(['/catalog/index'])], ['name' => $category->name, 'url' => null]]]) ?>
<h1 class="mb-4 text-2xl font-bold"><?= Html::encode($category->name) ?></h1>
<?= $this->render('_partials/filters', ['current' => $current, 'showCategory' => false]) ?>
<?= $this->render('_partials/_grid', ['dataProvider' => $dataProvider, 'empty' => 'No products here yet.']) ?>
