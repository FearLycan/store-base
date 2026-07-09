<?php

declare(strict_types=1);

namespace app\controllers;

use app\enums\ProductStatusEnum;
use app\enums\StoreStatusEnum;
use app\models\Category;
use app\models\Product;
use app\models\Store;
use app\models\StoreBanner;
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

        // Showcase mode: the plain store front — no narrowing filter, no explicit
        // sort, page 1. Only then do the hero/bestsellers/categories sections render
        // (and only then do we pay their queries); filtered or paginated views look
        // like the classic listing.
        $showcase = true;
        foreach (['min', 'max', 'rating', 'category', 'sale', 'video', 'sort', 'page', 'q'] as $key) {
            if (!empty($filters[$key])) {
                $showcase = false;
                break;
            }
        }

        $banners = $bestsellers = $topCategories = [];
        if ($showcase) {
            $banners = StoreBanner::forStore($store->id);
            $bestsellers = CatalogQuery::active()
                ->andWhere(['product.store_id' => $store->id])
                ->orderBy(['product.orders_count' => SORT_DESC])
                ->limit(6)
                ->all();
            $topCategories = $this->topCategories($store);
        }

        return $this->render('view', [
            'store'         => $store,
            'dataProvider'  => new ActiveDataProvider(['query' => $query, 'pagination' => new Pagination(['pageSize' => 24])]),
            'current'       => $filters,
            'categories'    => Category::excludeHidden(Category::find()->where(['level' => 1]))->orderBy(['name' => SORT_ASC])->all(),
            'showcase'      => $showcase,
            'banners'       => $banners,
            'bestsellers'   => $bestsellers,
            'topCategories' => $topCategories,
        ]);
    }

    /**
     * Up to $limit categories with the most active products in this store, each
     * fronted by the store's best-selling product photo in that category — the
     * store-scoped cousin of the homepage categoryCovers().
     *
     * @return array<int, array{category: Category, image: string|null, count: int}>
     */
    private function topCategories(Store $store, int $limit = 6): array
    {
        $rows = CatalogQuery::active()
            ->select(['product.category_id', 'n' => 'COUNT(*)'])
            ->andWhere(['product.store_id' => $store->id])
            ->andWhere(['not', ['product.category_id' => null]])
            ->groupBy('product.category_id')
            ->orderBy(['n' => SORT_DESC])
            ->limit($limit)
            ->asArray()
            ->all();
        if ($rows === []) {
            return [];
        }

        $ids = array_map(static fn (array $r): int => (int)$r['category_id'], $rows);
        $cats = Category::excludeHidden(Category::find()->where(['id' => $ids]))->indexBy('id')->all();

        $out = [];
        foreach ($rows as $row) {
            $catId = (int)$row['category_id'];
            if (!isset($cats[$catId])) {
                continue;
            }
            $image = CatalogQuery::active()
                ->select('product.main_image')
                ->andWhere(['product.store_id' => $store->id, 'product.category_id' => $catId])
                ->orderBy(['product.orders_count' => SORT_DESC])
                ->limit(1)
                ->scalar();
            $out[] = [
                'category' => $cats[$catId],
                'image'    => is_string($image) && $image !== '' ? $image : null,
                'count'    => (int)$row['n'],
            ];
        }

        return $out;
    }
}
