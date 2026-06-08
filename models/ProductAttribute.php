<?php

declare(strict_types=1);

namespace app\models;

use yii\db\ActiveRecord;

/**
 * @property int $id
 * @property int $product_id
 * @property string $name
 * @property string|null $value
 * @property int $position
 * @property int $created_at
 */
class ProductAttribute extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%product_attribute}}';
    }

    public function rules(): array
    {
        return [
            [['product_id', 'name'], 'required'],
            [['product_id', 'position', 'created_at'], 'integer'],
            [['name'], 'string', 'max' => 255],
            [['value'], 'string', 'max' => 1024],
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
