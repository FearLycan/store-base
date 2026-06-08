<?php

declare(strict_types=1);

namespace app\models;

use yii\db\ActiveRecord;

/**
 * @property int $id
 * @property int $review_id
 * @property string $url
 * @property int $position
 * @property int $created_at
 */
class ProductReviewImage extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%product_review_image}}';
    }

    public function rules(): array
    {
        return [
            [['review_id', 'url'], 'required'],
            [['review_id', 'position', 'created_at'], 'integer'],
            [['url'], 'string', 'max' => 1024],
        ];
    }

    public function beforeSave($insert): bool
    {
        if ($insert && $this->created_at === null) {
            $this->created_at = time();
        }

        return parent::beforeSave($insert);
    }
}
