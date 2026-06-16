<?php

declare(strict_types=1);

namespace app\services;

use app\models\Category;
use app\models\Product;
use yii\db\ActiveQuery;
use yii\db\Expression;

final class CatalogQuery
{
    public const SORTS = ['price_asc', 'price_desc', 'popular', 'rating', 'newest'];

    public static function active(): ActiveQuery
    {
        return Product::find()->where(['product.status' => 'active']);
    }

    /** Category subtree: the category plus its direct children (2-level hierarchy). */
    public static function inCategory(ActiveQuery $query, int $categoryId): ActiveQuery
    {
        $ids = Category::find()->select('id')->where(['parent_id' => $categoryId])->column();
        $ids[] = $categoryId;
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

    /** @return Product[] Active products carrying a playable video, most-ordered first. */
    public static function videos(int $limit = 12): array
    {
        return self::active()
            ->andWhere(['not', ['product.video_url' => null]])
            ->andWhere(['<>', 'product.video_url', ''])
            ->orderBy(['product.orders_count' => SORT_DESC])
            ->limit($limit)
            ->all();
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
