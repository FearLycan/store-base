<?php

declare(strict_types=1);

namespace app\models;

use yii\db\ActiveRecord;

/**
 * @property int $id
 * @property int $product_id
 * @property string|null $referrer
 * @property string|null $ua_hash
 * @property int $created_at
 */
class ProductClick extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%product_click}}';
    }

    public function rules(): array
    {
        return [
            [['product_id'], 'required'],
            [['product_id', 'created_at'], 'integer'],
            [['referrer'], 'string', 'max' => 1024],
            [['ua_hash'], 'string', 'max' => 64],
        ];
    }

    public function beforeSave($insert): bool
    {
        if ($insert && empty($this->created_at)) {
            $this->created_at = time();
        }
        return parent::beforeSave($insert);
    }
}
