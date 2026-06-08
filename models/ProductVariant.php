<?php

declare(strict_types=1);

namespace app\models;

use yii\behaviors\TimestampBehavior;
use yii\db\ActiveRecord;

/**
 * @property int $id
 * @property int $product_id
 * @property string|null $external_sku_id
 * @property string|null $name
 * @property array|null $options_json
 * @property int|null $price
 * @property int|null $original_price
 * @property int|null $stock
 * @property string|null $image
 * @property string|null $currency_code
 * @property int $created_at
 * @property int $updated_at
 */
class ProductVariant extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%product_variant}}';
    }

    public function behaviors(): array
    {
        return [TimestampBehavior::class];
    }

    public function rules(): array
    {
        return [
            [['product_id'], 'required'],
            [['product_id', 'price', 'original_price', 'stock'], 'integer'],
            [['options_json'], 'safe'],
            [['external_sku_id', 'currency_code'], 'string', 'max' => 64],
            [['name', 'image'], 'string', 'max' => 1024],
        ];
    }
}
