<?php
/** @var yii\web\View $this */
/** @var app\models\Category $category */
/** @var app\models\Category|null $parent */
/** @var app\models\Category[] $children */
/** @var yii\data\ActiveDataProvider $dataProvider */
/** @var array $current */
use app\components\Seo;
use app\components\schema\builder\ListingPageSchemaBuilder;
use app\components\schema\factory\FaqSchemaFactory;
use app\components\schema\JsonLdRenderer;
use yii\helpers\Html;
use yii\helpers\HtmlPurifier;
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
<?php
$faq = is_array($category->faq_json) ? $category->faq_json : [];
$schemaNodes = ListingPageSchemaBuilder::build($dataProvider, $schemaLinks, $home, $category->name, $category->name);
if ($faq !== []) { $schemaNodes[] = FaqSchemaFactory::fromPairs($faq); }
$intro = trim((string) $category->intro_html);
$cover = $cover ?? '';
?>
<?= JsonLdRenderer::render($schemaNodes) ?>
<?= $this->render('_partials/breadcrumbs', ['items' => $crumbs]) ?>
<h1 class="mb-4 text-2xl font-bold"><?= Html::encode($category->name) ?></h1>
<?php if ($intro !== ''): ?>
<?php if ($cover !== ''): ?>
<div class="mb-6 grid items-center gap-6 lg:grid-cols-2">
    <div class="cat-intro mb-0 max-w-none"><?= HtmlPurifier::process($intro) ?></div>
    <div class="overflow-hidden rounded-2xl border border-gray-200 bg-gray-100">
        <img src="<?= Html::encode($cover) ?>" alt="<?= Html::encode($category->name) ?>" class="aspect-[4/3] w-full object-cover" loading="lazy">
    </div>
</div>
<?php else: ?>
<div class="cat-intro"><?= HtmlPurifier::process($intro) ?></div>
<?php endif; ?>
<?php endif; ?>
<?= $this->render('_partials/subcategories', ['category' => $category, 'parent' => $parent, 'children' => $children]) ?>
<?= $this->render('_partials/filters', ['current' => $current, 'showCategory' => false]) ?>
<?= $this->render('_partials/active-filters', ['current' => $current, 'total' => $dataProvider->totalCount]) ?>
<?= $this->render('_partials/_grid', ['dataProvider' => $dataProvider, 'empty' => 'No products here yet.']) ?>
<?= $this->render('_partials/faq', ['faq' => $faq]) ?>
