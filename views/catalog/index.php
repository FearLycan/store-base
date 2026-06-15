<?php
/** @var yii\web\View $this */
/** @var app\models\Product[] $popular */
/** @var app\models\Product[] $newest */
/** @var app\models\Category[] $categories */
use app\components\Seo;
use app\components\schema\factory\OrganizationSchemaFactory;
use app\components\schema\JsonLdRenderer;
use yii\helpers\Html;
use yii\helpers\Url;

$name = (string)(Yii::$app->params['site.name'] ?? 'Store');
$tagline = (string)(Yii::$app->params['site.tagline'] ?? '');
Seo::apply($this, $name, $tagline !== '' ? $tagline : ('Browse ' . $name), Url::to(['/catalog/index'], true));

$rail = function (string $title, array $products) {
    if ($products === []) return;
    echo '<section class="mb-10"><h2 class="mb-4 text-xl font-bold">' . Html::encode($title) . '</h2>';
    echo '<div class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-6">';
    foreach ($products as $p) { echo $this->render('_partials/product-card', ['product' => $p]); }
    echo '</div></section>';
};
?>
<?= JsonLdRenderer::render([OrganizationSchemaFactory::fromParams()]) ?>

<section class="mb-8 rounded-2xl bg-white p-8 text-center">
    <h1 class="text-3xl font-bold"><?= Html::encode($name) ?></h1>
    <?php if ($tagline !== ''): ?><p class="mt-2 text-gray-500"><?= Html::encode($tagline) ?></p><?php endif; ?>
</section>

<section class="mb-10">
    <p class="mb-2 text-[11px] font-semibold uppercase tracking-wider text-gray-400">Refine the catalog</p>
    <?= $this->render('_partials/filters', ['current' => [], 'categories' => $categories, 'showCategory' => true, 'action' => Url::to(['/catalog/all'])]) ?>
</section>

<?php if ($categories !== []): ?>
<section class="mb-10">
    <h2 class="mb-4 text-xl font-bold">Categories</h2>
    <div class="flex flex-wrap gap-2">
        <?php foreach ($categories as $c): ?>
            <a href="<?= Url::to(['/catalog/category', 'slug' => $c->slug]) ?>" class="rounded-full border border-gray-300 bg-white px-4 py-2 text-sm hover:border-[color:var(--accent)]"><?= Html::encode($c->name) ?></a>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

<?php $rail->call($this, 'Most popular', $popular); ?>
<?php $rail->call($this, 'New arrivals', $newest); ?>
