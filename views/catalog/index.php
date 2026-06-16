<?php
/** @var yii\web\View $this */
/** @var app\models\Product[] $popular */
/** @var app\models\Product[] $newest */
/** @var app\models\Product[] $videos */
/** @var array<int, array{category: app\models\Category, image: string|null, count: int}> $categories */
use app\components\Seo;
use app\components\schema\factory\OrganizationSchemaFactory;
use app\components\schema\JsonLdRenderer;
use yii\helpers\Html;
use yii\helpers\Url;

$name = (string)(Yii::$app->params['site.name'] ?? 'Store');
$tagline = (string)(Yii::$app->params['site.tagline'] ?? '');
Seo::apply($this, $name, $tagline !== '' ? $tagline : ('Browse ' . $name), Url::to(['/catalog/index'], true));

$arrow = '<svg viewBox="0 0 16 16" fill="none" aria-hidden="true" class="h-3.5 w-3.5"><path d="M6 3.5 10.5 8 6 12.5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>';

$rail = function (string $title, array $products, string $moreUrl) use ($arrow) {
    if ($products === []) return;
    echo '<section class="mb-12">';
    echo '<div class="mb-4 flex items-baseline justify-between gap-4">';
    echo '<h2 class="text-xl font-bold tracking-tight">' . Html::encode($title) . '</h2>';
    echo '<a href="' . $moreUrl . '" class="rail-more">View all ' . $arrow . '</a>';
    echo '</div>';
    echo '<div class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-6">';
    foreach ($products as $p) { echo $this->render('_partials/product-card', ['product' => $p]); }
    echo '</div></section>';
};
?>
<?= JsonLdRenderer::render([OrganizationSchemaFactory::fromParams()]) ?>

<section class="mb-10 rounded-2xl bg-white p-8 text-center sm:p-12">
    <h1 class="text-3xl font-bold tracking-tight sm:text-4xl"><?= Html::encode($name) ?></h1>
    <?php if ($tagline !== ''): ?><p class="mx-auto mt-3 max-w-xl text-gray-500"><?= Html::encode($tagline) ?></p><?php endif; ?>
</section>

<?php if ($categories !== []): ?>
<section class="mb-12">
    <div class="mb-4 flex items-baseline justify-between gap-4">
        <h2 class="text-xl font-bold tracking-tight">Shop by category</h2>
        <a href="<?= Url::to(['/catalog/all']) ?>" class="rail-more">All products <?= $arrow ?></a>
    </div>
    <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 sm:gap-4 lg:grid-cols-4">
        <?php foreach ($categories as $cover): ?>
            <?= $this->render('_partials/category-tile', ['cover' => $cover]) ?>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

<section class="mb-12">
    <p class="mb-2 text-[11px] font-semibold uppercase tracking-wider text-gray-400">Refine the catalog</p>
    <?= $this->render('_partials/filters', ['current' => [], 'categories' => [], 'showCategory' => false, 'action' => Url::to(['/catalog/all'])]) ?>
</section>

<?php $rail->call($this, 'Most popular', $popular, Url::to(['/catalog/all', 'sort' => 'popular'])); ?>

<?= $this->render('_partials/video-wall', ['videos' => $videos]) ?>

<?php $rail->call($this, 'New arrivals', $newest, Url::to(['/catalog/all', 'sort' => 'newest'])); ?>
