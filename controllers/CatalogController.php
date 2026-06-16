<?php

declare(strict_types=1);

namespace app\controllers;

use app\models\Category;
use app\models\Product;
use app\services\CatalogQuery;
use Yii;
use yii\data\ActiveDataProvider;
use yii\data\Pagination;
use yii\helpers\Url;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\web\Response;

final class CatalogController extends Controller
{
    public $layout = 'shop';

    public function actions(): array
    {
        return [
            'error' => ['class' => \yii\web\ErrorAction::class],
        ];
    }

    public function actionIndex(): string
    {
        return $this->render('index', [
            'popular'    => CatalogQuery::rail('popular', 12),
            'newest'     => CatalogQuery::rail('newest', 12),
            'videos'     => CatalogQuery::videos(12),
            'categories' => self::categoryCovers(),
        ]);
    }

    /** Device-local wishlist: an SSR shell hydrated by the Alpine store from localStorage. */
    public function actionWishlist(): string
    {
        return $this->render('wishlist');
    }

    public function actionAll(): string
    {
        $filters = Yii::$app->request->queryParams;
        $query = CatalogQuery::applyFilters(CatalogQuery::active(), $filters);
        CatalogQuery::applySort($query, $filters['sort'] ?? 'popular');

        return $this->render('all', [
            'dataProvider' => new ActiveDataProvider(['query' => $query, 'pagination' => new Pagination(['pageSize' => 24])]),
            'current'      => $filters,
            'categories'   => self::topCategories(),
        ]);
    }

    public function actionCategory(string $slug): string
    {
        $category = Category::findOne(['slug' => $slug]) ?? throw new NotFoundHttpException('Category not found.');
        $filters = Yii::$app->request->queryParams;
        $query = CatalogQuery::applyFilters(CatalogQuery::inCategory(CatalogQuery::active(), $category->id), $filters);
        CatalogQuery::applySort($query, $filters['sort'] ?? 'popular');

        // Drill-down nav: a top-level category lists its own children; a sub-category
        // lists its siblings (the parent's children) so users can switch within the branch.
        $parent   = $category->level > 1 ? $category->parent : null;
        $children = Category::find()
            ->where(['parent_id' => $parent?->id ?? $category->id])
            ->orderBy(['name' => SORT_ASC])
            ->all();

        return $this->render('category', [
            'category'     => $category,
            'parent'       => $parent,
            'children'     => $children,
            'dataProvider' => new ActiveDataProvider(['query' => $query, 'pagination' => new Pagination(['pageSize' => 24])]),
            'current'      => $filters,
        ]);
    }

    public function actionSearch(): string
    {
        $q = (string) Yii::$app->request->get('q', '');
        $filters = Yii::$app->request->queryParams;
        $query = CatalogQuery::applyFilters(CatalogQuery::search($q), $filters);
        CatalogQuery::applySort($query, $filters['sort'] ?? 'popular');

        return $this->render('search', [
            'q'            => $q,
            'dataProvider' => new ActiveDataProvider(['query' => $query, 'pagination' => new Pagination(['pageSize' => 24])]),
            'current'      => $filters,
            'categories'   => self::topCategories(),
        ]);
    }

    /** @return Category[] Top-level categories for the filter bar's category select. */
    private static function topCategories(): array
    {
        return Category::find()->where(['level' => 1])->orderBy(['name' => SORT_ASC])->all();
    }

    /**
     * Top-level categories for the home page's visual grid, each with a cover
     * image (its best-selling product's photo) and an active-product count.
     * Empty categories are dropped and the richest are surfaced first, so the
     * grid never shows a dead tile. One light count + one scalar lookup per
     * category — fine for the handful of top-level categories a catalog has.
     *
     * @return array<int, array{category: Category, image: string|null, count: int}>
     */
    private static function categoryCovers(int $limit = 12): array
    {
        $covers = [];
        foreach (Category::find()->where(['level' => 1])->all() as $cat) {
            $base  = CatalogQuery::inCategory(CatalogQuery::active(), $cat->id);
            $count = (int) (clone $base)->count();
            if ($count === 0) {
                continue;
            }
            // Admin-set cover wins; otherwise fall back to the best-selling
            // product's photo (skipping that lookup when a custom image exists).
            $custom = trim((string) $cat->image_url);
            if ($custom !== '') {
                $image = $custom;
            } else {
                $best = (clone $base)
                    ->andWhere(['not', ['product.main_image' => null]])
                    ->andWhere(['<>', 'product.main_image', ''])
                    ->orderBy(['product.orders_count' => SORT_DESC])
                    ->select('product.main_image')
                    ->scalar();
                $image = is_string($best) && $best !== '' ? $best : null;
            }
            $covers[] = [
                'category' => $cat,
                'image'    => $image,
                'count'    => $count,
            ];
        }
        usort($covers, static fn (array $a, array $b): int => $b['count'] <=> $a['count']);

        return array_slice($covers, 0, $limit);
    }

    /** Live-search suggestions for the search modal. Empty query returns "popular now". */
    public function actionSuggest(): array
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        $q = trim((string) Yii::$app->request->get('q', ''));

        if ($q === '') {
            return [
                'q'          => $q,
                'mode'       => 'popular',
                'total'      => 0,
                'categories' => array_map(self::categoryPayload(...), Category::find()
                    ->where(['level' => 1])->orderBy(['name' => SORT_ASC])->limit(6)->all()),
                'products'   => array_map(self::productPayload(...), CatalogQuery::rail('popular', 6)),
            ];
        }

        $query = CatalogQuery::search($q);
        $total = (int) (clone $query)->count();
        $products = CatalogQuery::applySort($query, 'popular')->limit(8)->all();
        $categories = Category::find()->where(['like', 'name', $q])
            ->orderBy(['level' => SORT_ASC, 'name' => SORT_ASC])->limit(4)->all();

        return [
            'q'          => $q,
            'mode'       => 'results',
            'total'      => $total,
            'categories' => array_map(self::categoryPayload(...), $categories),
            'products'   => array_map(self::productPayload(...), $products),
        ];
    }

    /**
     * Image URLs for a single product, main image first, deduped and capped.
     * Fed to the catalog card's on-hover mini gallery. Returns an empty list
     * for unknown/inactive products so the card silently stays on its poster.
     */
    public function actionImages(int $id): array
    {
        Yii::$app->response->format = Response::FORMAT_JSON;

        $product = CatalogQuery::active()->andWhere(['product.id' => $id])->with('images')->one();
        if ($product === null) {
            return ['images' => []];
        }

        $urls = [];
        if ($product->main_image) {
            $urls[] = (string) $product->main_image;
        }
        foreach ($product->images as $img) {
            $urls[] = (string) $img->url;
        }

        $urls = array_values(array_unique(array_filter($urls, static fn($u): bool => $u !== '')));

        return ['images' => array_slice($urls, 0, 6)];
    }

    private static function categoryPayload(Category $category): array
    {
        return [
            'name' => $category->name,
            'url'  => Url::to(['/catalog/category', 'slug' => $category->slug]),
        ];
    }

    private static function productPayload(Product $product): array
    {
        $hasSale = $product->price !== null && $product->original_price !== null && $product->original_price > $product->price;

        return [
            'title'         => $product->displayName,
            'url'           => Url::to(['/product/view', 'slug' => $product->slug]),
            'image'         => $product->main_image ?: '/img/placeholder.png',
            'price'         => $product->price !== null ? number_format($product->price / 100, 2) : null,
            'originalPrice' => $hasSale ? number_format($product->original_price / 100, 2) : null,
            'discount'      => $hasSale ? (int) round((1 - $product->price / $product->original_price) * 100) : null,
            'currency'      => $product->currency_code ?: 'USD',
            'rating'        => $product->rating_value !== null ? round((float) $product->rating_value, 1) : null,
            'reviews'       => $product->review_count,
            'orders'        => $product->orders_count > 0 ? self::shortCount($product->orders_count) : null,
        ];
    }

    /** 1432 -> "1.4k", 2100000 -> "2.1m" */
    private static function shortCount(int $n): string
    {
        if ($n >= 1_000_000) {
            return rtrim(rtrim(number_format($n / 1_000_000, 1), '0'), '.') . 'm';
        }
        if ($n >= 1_000) {
            return rtrim(rtrim(number_format($n / 1_000, 1), '0'), '.') . 'k';
        }
        return (string) $n;
    }
}
