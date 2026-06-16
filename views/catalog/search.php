<?php
/** @var yii\web\View $this */
/** @var string $q */
/** @var yii\data\ActiveDataProvider $dataProvider */
/** @var array $current */
/** @var app\models\Category[] $categories */
use app\components\Seo;
use yii\helpers\Html;
use yii\helpers\Url;

Seo::apply($this, $q !== '' ? ('Search: ' . $q) : 'Search', 'Search results', Url::current([], true), true);
?>
<h1 class="mb-4 text-2xl font-bold"><?= $q !== '' ? 'Results for “' . Html::encode($q) . '”' : 'Search' ?></h1>
<?= $this->render('_partials/filters', ['current' => $current, 'categories' => $categories, 'showCategory' => true]) ?>
<?= $this->render('_partials/active-filters', ['current' => $current, 'total' => $dataProvider->totalCount, 'categories' => $categories]) ?>
<?= $this->render('_partials/_grid', ['dataProvider' => $dataProvider, 'empty' => ($q !== '' ? 'No results for “' . Html::encode($q) . '”.' : 'Type a query to search.')]) ?>
