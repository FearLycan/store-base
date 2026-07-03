<?php

declare(strict_types=1);

namespace app\models;

use app\enums\StoreStatusEnum;
use yii\behaviors\TimestampBehavior;
use yii\db\ActiveQuery;
use yii\db\ActiveRecord;
use yii\helpers\Inflector;

/**
 * @property int $id
 * @property string $external_store_id
 * @property string|null $seller_id
 * @property string $name
 * @property string|null $slug
 * @property string|null $image_url
 * @property string $url
 * @property string|null $website_url
 * @property string|null $instagram_url
 * @property string|null $facebook_url
 * @property string|null $tiktok_url
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
            [['slug'], 'unique'],
            [['external_store_id', 'seller_id', 'seller_admin_seq', 'status'], 'string', 'max' => 64],
            [['name', 'slug'], 'string', 'max' => 255],
            [['url', 'image_url', 'website_url', 'instagram_url', 'facebook_url', 'tiktok_url'], 'string', 'max' => 1024],
            [['website_url', 'instagram_url', 'facebook_url', 'tiktok_url'], 'url', 'defaultScheme' => 'https', 'skipOnEmpty' => true],
            [['last_discovery_at', 'last_full_sync_at', 'product_count'], 'integer'],
            [['status'], 'default', 'value' => StoreStatusEnum::ACTIVE->value],
        ];
    }

    public function beforeSave($insert): bool
    {
        if (!parent::beforeSave($insert)) {
            return false;
        }
        // Every store needs a slug for its public /store/<slug> page; derive one from
        // the name the first time it's missing, keeping it unique across stores.
        if ($this->slug === null || $this->slug === '') {
            $this->slug = self::generateUniqueSlug((string) $this->name, $this->id);
        }

        return true;
    }

    /** Slugify $base and append -2, -3, … until it is unique (optionally ignoring one row). */
    public static function generateUniqueSlug(string $base, ?int $excludeId = null): string
    {
        $slug = Inflector::slug($base);
        if ($slug === '') {
            $slug = 'store';
        }
        $candidate = $slug;
        $i = 2;
        while (self::slugExists($candidate, $excludeId)) {
            $candidate = $slug . '-' . $i++;
        }

        return $candidate;
    }

    private static function slugExists(string $slug, ?int $excludeId): bool
    {
        $query = self::find()->where(['slug' => $slug]);
        if ($excludeId !== null) {
            $query->andWhere(['<>', 'id', $excludeId]);
        }

        return $query->exists();
    }

    /**
     * Active stores that hold at least one active product — for the storefront's store
     * filter, so no option leads to an empty listing. Ordered by name.
     *
     * @return self[]
     */
    public static function withActiveProducts(): array
    {
        $ids = Product::find()
            ->select('store_id')
            ->distinct()
            ->where(['status' => \app\enums\ProductStatusEnum::ACTIVE->value])
            ->column();
        if ($ids === []) {
            return [];
        }

        return self::find()
            ->where(['id' => array_map('intval', $ids), 'status' => StoreStatusEnum::ACTIVE->value])
            ->orderBy(['name' => SORT_ASC])
            ->all();
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
