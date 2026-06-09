<?php

declare(strict_types=1);

namespace app\controllers;

use app\models\Category;
use app\services\CatalogQuery;
use Yii;
use yii\data\ActiveDataProvider;
use yii\data\Pagination;
use yii\web\Controller;
use yii\web\NotFoundHttpException;

final class CatalogController extends Controller
{
    public $layout = 'shop';

    public function actionIndex(): string
    {
        return $this->render('index', [
            'popular' => CatalogQuery::rail('popular', 12),
            'newest'  => CatalogQuery::rail('newest', 12),
            'categories' => Category::find()->where(['level' => 1])->orderBy(['name' => SORT_ASC])->all(),
        ]);
    }

    public function actionAll(): string
    {
        $filters = Yii::$app->request->queryParams;
        $query = CatalogQuery::applyFilters(CatalogQuery::active(), $filters);
        CatalogQuery::applySort($query, $filters['sort'] ?? 'popular');

        return $this->render('all', [
            'dataProvider' => new ActiveDataProvider(['query' => $query, 'pagination' => new Pagination(['pageSize' => 24])]),
            'current' => $filters,
        ]);
    }

    public function actionCategory(string $slug): string
    {
        $category = Category::findOne(['slug' => $slug]) ?? throw new NotFoundHttpException('Category not found.');
        $filters = Yii::$app->request->queryParams;
        $query = CatalogQuery::applyFilters(CatalogQuery::inCategory(CatalogQuery::active(), $category->id), $filters);
        CatalogQuery::applySort($query, $filters['sort'] ?? 'popular');

        return $this->render('category', [
            'category' => $category,
            'dataProvider' => new ActiveDataProvider(['query' => $query, 'pagination' => new Pagination(['pageSize' => 24])]),
            'current' => $filters,
        ]);
    }

    public function actionSearch(): string
    {
        $q = (string)Yii::$app->request->get('q', '');
        $filters = Yii::$app->request->queryParams;
        $query = CatalogQuery::applyFilters(CatalogQuery::search($q), $filters);
        CatalogQuery::applySort($query, $filters['sort'] ?? 'popular');

        return $this->render('search', [
            'q' => $q,
            'dataProvider' => new ActiveDataProvider(['query' => $query, 'pagination' => new Pagination(['pageSize' => 24])]),
            'current' => $filters,
        ]);
    }
}
