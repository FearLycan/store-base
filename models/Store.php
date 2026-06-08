<?php

declare(strict_types=1);

namespace app\models;

use app\enums\StoreStatusEnum;
use yii\behaviors\TimestampBehavior;
use yii\db\ActiveQuery;
use yii\db\ActiveRecord;

/**
 * @property int $id
 * @property string $external_store_id
 * @property string $name
 * @property string $url
 * @property string|null $seller_admin_seq
 * @property string $status
 * @property int|null $last_discovery_at
 * @property int|null $last_full_sync_at
 * @property int $product_count
 * @property int $created_at
 * @property int $updated_at
 *
 * @property Product[] $products
 */
class Store extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%store}}';
    }

    public function behaviors(): array
    {
        return [TimestampBehavior::class];
    }

    public function rules(): array
    {
        return [
            [['external_store_id', 'name', 'url'], 'required'],
            [['external_store_id'], 'unique'],
            [['external_store_id', 'seller_admin_seq', 'status'], 'string', 'max' => 64],
            [['name'], 'string', 'max' => 255],
            [['url'], 'string', 'max' => 1024],
            [['last_discovery_at', 'last_full_sync_at', 'product_count'], 'integer'],
            [['status'], 'default', 'value' => StoreStatusEnum::ACTIVE->value],
        ];
    }

    public function getProducts(): ActiveQuery
    {
        return $this->hasMany(Product::class, ['store_id' => 'id']);
    }

    public function isActive(): bool
    {
        return $this->status === StoreStatusEnum::ACTIVE->value;
    }

    public function recountProducts(): void
    {
        $this->product_count = (int)Product::find()->where(['store_id' => $this->id])->count();
        $this->save(false, ['product_count']);
    }
}
