<?php
/** @var yii\web\View $this */
/** @var app\models\Store $store */
/** @var yii\data\ActiveDataProvider $dataProvider */
/** @var array $current */
/** @var app\models\Category[] $categories */

use app\components\schema\builder\ListingPageSchemaBuilder;
use app\components\schema\JsonLdRenderer;
use app\components\Seo;
use yii\helpers\Html;
use yii\helpers\Url;

$canonical = Url::to(['/store/view', 'slug' => $store->slug], true);
Seo::apply($this, $store->name, 'Browse products sold by ' . $store->name . '.', $canonical);
$home = ['label' => 'Home', 'url' => Url::to(['/catalog/index'])];

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
    </div>
</div>

<?= $this->render('//catalog/_partials/filters', ['current' => $current, 'categories' => $categories, 'showCategory' => true, 'showStore' => false]) ?>
<?= $this->render('//catalog/_partials/active-filters', ['current' => $current, 'total' => $dataProvider->totalCount, 'categories' => $categories]) ?>
<?= $this->render('//catalog/_partials/_grid', ['dataProvider' => $dataProvider, 'empty' => 'No products from this store yet.']) ?>
