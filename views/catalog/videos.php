<?php
/** @var yii\web\View $this */
/** @var yii\data\ActiveDataProvider $dataProvider */
use app\components\Seo;
use yii\helpers\Html;
use yii\helpers\Url;

// Prepare first so the paginator learns its totalCount before Seo reads the page number.
$dataProvider->prepare();
Seo::apply($this, 'Product videos', 'Watch real product clips, then jump straight to the item.', Url::to(['/catalog/videos'], true), false, '', $dataProvider->getPagination());

/** @var app\models\Product[] $videos */
$videos = $dataProvider->getModels();
?>
<?= $this->render('_partials/breadcrumbs', ['items' => [['name' => 'Home', 'url' => Url::to(['/catalog/index'])], ['name' => 'Videos', 'url' => null]]]) ?>
<h1 class="mb-1 text-2xl font-bold">Product videos</h1>
<p class="mb-6 text-sm text-gray-500">Real product clips — tap to watch, then jump straight to the item.</p>

<?php if ($videos === []): ?>
    <p class="rounded-xl border border-gray-200 bg-white p-8 text-center text-gray-500">No videos yet.</p>
<?php else: ?>
    <?= $this->render('_partials/video-player', ['videos' => $videos, 'layout' => 'grid']) ?>
    <?= $this->render('_partials/pagination', ['pages' => $dataProvider->pagination]) ?>
<?php endif; ?>
