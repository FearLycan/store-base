<?php

declare(strict_types=1);

namespace app\services;

use app\models\Category;
use app\models\Product;
use Yii;
use yii\db\ActiveQuery;
use yii\db\Expression;

final class CatalogQuery
{
    public const SORTS = ['price_asc', 'price_desc', 'popular', 'rating', 'newest'];

    public static function active(): ActiveQuery
    {
        $query = Product::find()->where(['product.status' => 'active']);

        // Hide products whose category (or any ancestor) is inactive. Products
        // with no category are unaffected. Covers every storefront listing,
        // since they all build on this query.
        $hidden = Category::hiddenIds();
        if ($hidden !== []) {
            $query->andWhere(['or',
                ['product.category_id' => null],
                ['not in', 'product.category_id', $hidden],
            ]);
        }

        return $query;
    }

    /** Category subtree: the category plus all descendants (L1 → L2 → item-type L3). */
    public static function inCategory(ActiveQuery $query, int $categoryId): ActiveQuery
    {
        $ids = [$categoryId];
        $frontier = [$categoryId];
        while ($frontier !== []) {
            $children = Category::find()->select('id')->where(['parent_id' => $frontier])->column();
            $children = array_values(array_diff(array_map('intval', $children), $ids));
            if ($children === []) {
                break;
            }
            $ids = array_merge($ids, $children);
            $frontier = $children;
        }
        return $query->andWhere(['product.category_id' => $ids]);
    }

    public static function applyFilters(ActiveQuery $query, array $f): ActiveQuery
    {
        if (isset($f['min']) && is_numeric($f['min'])) {
            $query->andWhere(['>=', 'product.price', (int)round((float)$f['min'] * 100)]);
        }
        if (isset($f['max']) && is_numeric($f['max'])) {
            $query->andWhere(['<=', 'product.price', (int)round((float)$f['max'] * 100)]);
        }
        if (isset($f['rating']) && is_numeric($f['rating'])) {
            $query->andWhere(['>=', 'product.rating_value', (float)$f['rating']]);
        }
        if (isset($f['category']) && (int)$f['category'] > 0) {
            self::inCategory($query, (int)$f['category']);
        }
        if (isset($f['store']) && (int)$f['store'] > 0) {
            $query->andWhere(['product.store_id' => (int)$f['store']]);
        }
        if (!empty($f['sale'])) {
            $query->andWhere('product.original_price IS NOT NULL AND product.price IS NOT NULL AND product.original_price > product.price');
        }
        if (!empty($f['video'])) {
            $query->andWhere(['not', ['product.video_url' => null]])->andWhere(['<>', 'product.video_url', '']);
        }
        return $query;
    }

    public static function applySort(ActiveQuery $query, ?string $sort): ActiveQuery
    {
        return match ($sort) {
            'price_asc'  => $query->orderBy(['product.price' => SORT_ASC]),
            'price_desc' => $query->orderBy(['product.price' => SORT_DESC]),
            'rating'     => $query->orderBy(['product.rating_value' => SORT_DESC]),
            'newest'     => $query->orderBy(['product.first_imported_at' => SORT_DESC]),
            default      => $query->orderBy(['product.orders_count' => SORT_DESC]), // 'popular'
        };
    }

    public static function search(string $q): ActiveQuery
    {
        $query = self::active();
        $q = trim($q);
        if ($q === '') { return $query->andWhere('0=1'); }
        if (mb_strlen($q) >= 4) {
            return $query->andWhere(new Expression('MATCH(product.title) AGAINST (:q IN NATURAL LANGUAGE MODE)', [':q' => $q]));
        }
        return $query->andWhere(['like', 'product.title', $q]);
    }

    /** @return Product[] */
    public static function rail(string $sort, int $limit = 12): array
    {
        return self::applySort(self::active(), $sort)->limit($limit)->all();
    }

    /** Active products that carry a playable clip — base query for the video strip and /videos listing. */
    public static function withVideo(): ActiveQuery
    {
        return self::active()
            ->andWhere(['not', ['product.video_url' => null]])
            ->andWhere(['<>', 'product.video_url', '']);
    }

    /**
     * A rotating pick of active products carrying a playable video, for the home strip.
     *
     * Rather than always showing the same most-ordered clips, we take a pool of the
     * top sellers and rotate a random window over it that changes every hour. The
     * pick is cached for that hour (keyed by the time window), so repeated visits
     * within the hour hit the cache and the strip only re-rolls once it expires.
     *
     * @return Product[]
     */
    public static function videos(int $limit = 12): array
    {
        $window = 3600;                       // rotate (and cache) once an hour
        $bucket = intdiv(time(), $window);
        $key    = ['catalog.videos', $limit, $bucket];

        $ids = Yii::$app->cache->getOrSet($key, static function () use ($limit): array {
            // A pool a few times the strip size so each hour surfaces a fresh slice
            // while staying within genuine top sellers.
            $pool = array_map('intval', self::withVideo()
                ->orderBy(['product.orders_count' => SORT_DESC])
                ->limit(max($limit * 4, 48))
                ->select('product.id')
                ->column());
            shuffle($pool);

            return array_slice($pool, 0, $limit);
        }, $window);

        if ($ids === []) {
            return [];
        }

        // Fetch fresh rows for the cached ids (so prices/titles never go stale),
        // preserving the rolled order.
        $byId = self::active()->andWhere(['product.id' => $ids])->indexBy('id')->all();
        $ordered = [];
        foreach ($ids as $id) {
            if (isset($byId[$id])) {
                $ordered[] = $byId[$id];
            }
        }

        return $ordered;
    }

    /** @return Product[] */
    public static function related(Product $product, int $limit = 8): array
    {
        $query = self::active()->andWhere(['<>', 'product.id', $product->id]);
        if ($product->category_id !== null) {
            $query->andWhere(['product.category_id' => $product->category_id]);
        }
        return $query->orderBy(['product.orders_count' => SORT_DESC])->limit($limit)->all();
    }
}
