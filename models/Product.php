<?php

declare(strict_types=1);

namespace app\models;

use app\enums\ProductStatusEnum;
use yii\behaviors\SluggableBehavior;
use yii\behaviors\TimestampBehavior;
use yii\db\ActiveQuery;
use yii\db\ActiveRecord;

/**
 * @property int $id
 * @property int $store_id
 * @property int|null $category_id
 * @property string $external_id
 * @property string|null $title
 * @property string|null $slug
 * @property string|null $description
 * @property string|null $main_image
 * @property string|null $video_url
 * @property string|null $product_url
 * @property string|null $affiliate_url
 * @property string $currency_code
 * @property int|null $price
 * @property int|null $original_price
 * @property string|null $rating_value
 * @property string|null $rating_scale_max
 * @property int $review_count
 * @property int $orders_count
 * @property string|null $availability
 * @property string $status
 * @property string $source
 * @property array|null $review_impressions
 * @property int|null $first_imported_at
 * @property int|null $last_detail_synced_at
 * @property int|null $last_price_synced_at
 * @property int|null $last_review_synced_at
 * @property int $created_at
 * @property int $updated_at
 *
 * @property Store $store
 * @property Category|null $category
 * @property ProductImage[] $images
 * @property ProductVariant[] $variants
 * @property ProductAttribute[] $specs
 * @property ProductReview[] $reviews
 */
class Product extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%product}}';
    }

    public function behaviors(): array
    {
        return [
            TimestampBehavior::class,
            'sluggable' => [
                'class' => SluggableBehavior::class,
                'attribute' => 'title',
                'slugAttribute' => 'slug',
                'ensureUnique' => true,
                'immutable' => true,
            ],
        ];
    }

    public function rules(): array
    {
        return [
            [['store_id', 'external_id'], 'required'],
            [['store_id', 'category_id', 'price', 'original_price', 'review_count', 'orders_count',
              'first_imported_at', 'last_detail_synced_at', 'last_price_synced_at', 'last_review_synced_at'], 'integer'],
            [['description'], 'string'],
            [['review_impressions'], 'safe'],
            [['rating_value', 'rating_scale_max'], 'number'],
            [['external_id', 'currency_code', 'availability', 'status', 'source'], 'string', 'max' => 64],
            [['title', 'slug'], 'string', 'max' => 512],
            [['main_image', 'video_url', 'product_url', 'affiliate_url'], 'string', 'max' => 1024],
            [['status'], 'default', 'value' => ProductStatusEnum::ACTIVE->value],
            [['currency_code'], 'default', 'value' => 'USD'],
        ];
    }

    public function getStore(): ActiveQuery
    {
        return $this->hasOne(Store::class, ['id' => 'store_id']);
    }

    public function getCategory(): ActiveQuery
    {
        return $this->hasOne(Category::class, ['id' => 'category_id']);
    }

    public function getImages(): ActiveQuery
    {
        return $this->hasMany(ProductImage::class, ['product_id' => 'id'])->orderBy(['position' => SORT_ASC]);
    }

    public function getVariants(): ActiveQuery
    {
        return $this->hasMany(ProductVariant::class, ['product_id' => 'id']);
    }

    public function getSpecs(): ActiveQuery
    {
        // Named getSpecs() (not getAttributes()) to avoid clashing with ActiveRecord::getAttributes().
        return $this->hasMany(ProductAttribute::class, ['product_id' => 'id'])->orderBy(['position' => SORT_ASC]);
    }

    public function getReviews(): ActiveQuery
    {
        return $this->hasMany(ProductReview::class, ['product_id' => 'id'])->orderBy(['reviewed_at' => SORT_DESC]);
    }

    /**
     * Find existing or build a new product for (store, external_id). Does not save.
     */
    public static function findOrNew(int $storeId, string $externalId): self
    {
        $product = self::findOne(['store_id' => $storeId, 'external_id' => $externalId]);
        if ($product === null) {
            $product = new self();
            $product->store_id = $storeId;
            $product->external_id = $externalId;
            $product->first_imported_at = time();
        }

        return $product;
    }
}
