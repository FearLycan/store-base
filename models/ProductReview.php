<?php

declare(strict_types=1);

namespace app\models;

use yii\db\ActiveQuery;
use yii\db\ActiveRecord;

/**
 * @property int $id
 * @property int $product_id
 * @property string|null $external_review_id
 * @property string|null $author_name
 * @property string|null $reviewer_country
 * @property string|null $rating_value
 * @property string|null $rating_scale_max
 * @property string|null $content
 * @property int|null $reviewed_at
 * @property int $created_at
 *
 * @property ProductReviewImage[] $images
 */
class ProductReview extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%product_review}}';
    }

    public function rules(): array
    {
        return [
            [['product_id'], 'required'],
            [['product_id', 'reviewed_at', 'created_at'], 'integer'],
            [['content'], 'string'],
            [['rating_value', 'rating_scale_max'], 'number'],
            [['external_review_id', 'reviewer_country'], 'string', 'max' => 64],
            [['author_name'], 'string', 'max' => 255],
        ];
    }

    public function beforeSave($insert): bool
    {
        if ($insert && $this->created_at === null) {
            $this->created_at = time();
        }

        return parent::beforeSave($insert);
    }

    public function getImages(): ActiveQuery
    {
        return $this->hasMany(ProductReviewImage::class, ['review_id' => 'id'])->orderBy(['position' => SORT_ASC]);
    }

    /**
     * Upsert a batch of normalized review items for a product.
     * $items: list of ['external_review_id','author_name','reviewer_country',
     *   'reviewed_at'(int|null),'content','rating_value','rating_scale_max','images'(string[])].
     */
    public static function syncByProduct(Product $product, array $items): int
    {
        $saved = 0;
        foreach ($items as $item) {
            $externalId = isset($item['external_review_id']) ? (string)$item['external_review_id'] : '';
            $review = null;
            if ($externalId !== '') {
                $review = self::findOne(['product_id' => $product->id, 'external_review_id' => $externalId]);
            }
            if ($review === null) {
                $review = new self();
                $review->product_id = $product->id;
                $review->external_review_id = $externalId !== '' ? $externalId : null;
            }

            $review->author_name      = $item['author_name'] ?? null;
            $review->reviewer_country = $item['reviewer_country'] ?? null;
            $review->reviewed_at      = isset($item['reviewed_at']) ? (int)$item['reviewed_at'] : null;
            $review->content          = $item['content'] ?? null;
            $review->rating_value     = isset($item['rating_value']) ? (string)$item['rating_value'] : null;
            $review->rating_scale_max = isset($item['rating_scale_max']) ? (string)$item['rating_scale_max'] : null;
            if (!$review->save()) {
                continue;
            }
            $saved++;

            ProductReviewImage::deleteAll(['review_id' => $review->id]);
            $position = 0;
            foreach (($item['images'] ?? []) as $url) {
                $image = new ProductReviewImage();
                $image->review_id = $review->id;
                $image->url = (string)$url;
                $image->position = $position++;
                $image->save();
            }
        }

        $product->review_count = (int)self::find()->where(['product_id' => $product->id])->count();
        $product->last_review_synced_at = time();
        $product->save(false, ['review_count', 'last_review_synced_at']);

        return $saved;
    }
}
