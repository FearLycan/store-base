<?php

declare(strict_types=1);

namespace app\models;

use yii\db\ActiveRecord;

/**
 * @property int $id
 * @property int $product_id
 * @property string $url
 * @property int $position
 * @property int $is_main
 * @property int $created_at
 */
class ProductImage extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%product_image}}';
    }

    public function rules(): array
    {
        return [
            [['product_id', 'url'], 'required'],
            [['product_id', 'position', 'is_main', 'created_at'], 'integer'],
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
