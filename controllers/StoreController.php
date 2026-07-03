<?php

declare(strict_types=1);

namespace app\controllers;

use app\enums\ProductStatusEnum;
use app\enums\StoreStatusEnum;
use app\models\Category;
use app\models\Product;
use app\models\Store;
use app\services\CatalogQuery;
use Yii;
use yii\data\ActiveDataProvider;
use yii\data\Pagination;
use yii\web\Controller;
use yii\web\NotFoundHttpException;

final class StoreController extends Controller
{
    public $layout = 'shop';

    /** Directory of active stores that hold at least one active product. */
    public function actionIndex(): string
    {
        // One grouped query for the live per-store active-product counts, so the
        // cards show the real storefront number (not the raw product_count column).
        $rows = Product::find()
            ->select(['store_id', 'n' => 'COUNT(*)'])
            ->where(['status' => ProductStatusEnum::ACTIVE->value])
            ->groupBy('store_id')
            ->asArray()
            ->all();
        $counts = [];
        foreach ($rows as $row) {
            $counts[(int) $row['store_id']] = (int) $row['n'];
        }

        $stores = Store::find()
            ->where(['id' => array_keys($counts), 'status' => StoreStatusEnum::ACTIVE->value])
            ->orderBy(['name' => SORT_ASC])
            ->all();

        return $this->render('index', [
            'stores' => $stores,
            'counts' => $counts,
        ]);
    }

    public function actionView(string $slug): string
    {
        // A paused store is hidden from the storefront, so its page 404s like a missing one.
        $store = Store::find()
            ->where(['slug' => $slug, 'status' => StoreStatusEnum::ACTIVE->value])
            ->one();
        if ($store === null) {
            throw new NotFoundHttpException('Store not found.');
        }

        $filters = Yii::$app->request->queryParams;
        $query = CatalogQuery::applyFilters(
            CatalogQuery::active()->andWhere(['product.store_id' => $store->id]),
            $filters,
        );
        CatalogQuery::applySort($query, $filters['sort'] ?? 'popular');

        return $this->render('view', [
            'store'        => $store,
            'dataProvider' => new ActiveDataProvider(['query' => $query, 'pagination' => new Pagination(['pageSize' => 24])]),
            'current'      => $filters,
            'categories'   => Category::excludeHidden(Category::find()->where(['level' => 1]))->orderBy(['name' => SORT_ASC])->all(),
        ]);
    }
}
