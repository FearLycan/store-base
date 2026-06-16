<?php

declare(strict_types=1);

namespace app\models;

use yii\db\ActiveQuery;
use yii\db\ActiveRecord;

/**
 * One recorded price point for a product (a row per change + first import).
 *
 * @property int $id
 * @property int $product_id
 * @property int|null $price
 * @property int|null $original_price
 * @property string|null $currency_code
 * @property int $recorded_at
 *
 * @property Product $product
 */
class ProductPriceHistory extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%product_price_history}}';
    }

    /** Append a price point. Best-effort (save without validation). */
    public static function add(int $productId, ?int $price, ?int $originalPrice, ?string $currency, int $at): void
    {
        $row = new self();
        $row->product_id = $productId;
        $row->price = $price;
        $row->original_price = $originalPrice;
        $row->currency_code = $currency;
        $row->recorded_at = $at;
        $row->save(false);
    }

    public function getProduct(): ActiveQuery
    {
        return $this->hasOne(Product::class, ['id' => 'product_id']);
    }
}
