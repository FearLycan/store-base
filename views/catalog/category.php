<?php
/** @var yii\web\View $this */
/** @var app\models\Category $category */
/** @var app\models\Category|null $parent */
/** @var app\models\Category $branch */
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

// Breadcrumb trail: Home > [ancestors…] > current — the full chain (L1 > L2 > L3), not just the
// immediate parent. `$schemaLinks` are the intermediate links for the JSON-LD breadcrumb; `$crumbs`
// feed the visible one.
$ancestors = [];
for ($a = $category->parent; $a !== null; $a = $a->parent) {
    $ancestors[] = $a;
}
$ancestors = array_reverse($ancestors);

$schemaLinks = [];
$crumbs = [['name' => 'Home', 'url' => Url::to(['/catalog/index'])]];
foreach ($ancestors as $ancestor) {
    $ancestorUrl = Url::to(['/catalog/category', 'slug' => $ancestor->slug]);
    $schemaLinks[] = ['label' => $ancestor->name, 'url' => $ancestorUrl];
    $crumbs[] = ['name' => $ancestor->name, 'url' => $ancestorUrl];
}
$crumbs[] = ['name' => $category->name, 'url' => null];
?>
<?php
$faq = is_array($category->faq_json) ? $category->faq_json : [];
$schemaNodes = ListingPageSchemaBuilder::build($dataProvider, $schemaLinks, $home, $category->name, $category->name);
if ($faq !== []) { $schemaNodes[] = FaqSchemaFactory::fromPairs($faq); }
$intro = trim((string) $category->intro_html);
$cover = $cover ?? '';
// The intro hero is landing content: hide it once the user narrows with a filter
// or pages past the first page, leaving just the results.
$heroActive = false;
foreach (['min', 'max', 'rating', 'category', 'sale', 'video', 'q'] as $heroKey) {
    if (isset($current[$heroKey]) && trim((string) $current[$heroKey]) !== '') { $heroActive = true; break; }
}
$showHero = !$heroActive && (int) ($current['page'] ?? 1) <= 1;
?>
<?= JsonLdRenderer::render($schemaNodes) ?>
<?= $this->render('_partials/breadcrumbs', ['items' => $crumbs]) ?>
<h1 class="mb-4 text-2xl font-bold"><?= Html::encode($category->name) ?></h1>
<?php if ($intro !== '' && $showHero): ?>
<?php if ($cover !== ''): ?>
<div class="mb-6 grid items-center gap-6 lg:grid-cols-3">
    <div class="cat-intro mb-0 max-w-none lg:col-span-2"><?= HtmlPurifier::process($intro) ?></div>
    <div class="overflow-hidden rounded-2xl border border-gray-200 bg-gray-100">
        <img src="<?= Html::encode($cover) ?>" alt="<?= Html::encode($category->name) ?>" class="h-56 w-full object-cover sm:h-64" loading="lazy">
    </div>
</div>
<?php else: ?>
<div class="cat-intro"><?= HtmlPurifier::process($intro) ?></div>
<?php endif; ?>
<?php endif; ?>
<?= $this->render('_partials/subcategories', ['category' => $category, 'branch' => $branch, 'children' => $children]) ?>
<?= $this->render('_partials/filters', ['current' => $current, 'showCategory' => false]) ?>
<?= $this->render('_partials/active-filters', ['current' => $current, 'total' => $dataProvider->totalCount]) ?>
<?= $this->render('_partials/_grid', ['dataProvider' => $dataProvider, 'empty' => 'No products here yet.']) ?>
<?= $this->render('_partials/faq', ['faq' => $faq]) ?>
