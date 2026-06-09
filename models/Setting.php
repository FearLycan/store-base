<?php

declare(strict_types=1);

namespace app\models;

use yii\db\ActiveRecord;

/**
 * Generic key/value setting persisted in the DB (admin-editable at runtime, unlike
 * params.php). Currently used for the AliExpress session cookie (incl. x5sec).
 *
 * @property string $name
 * @property string|null $value
 * @property int $updated_at
 */
class Setting extends ActiveRecord
{
    public const ALIEXPRESS_COOKIE = 'aliexpress.cookie';

    public static function tableName(): string
    {
        return '{{%setting}}';
    }

    public function rules(): array
    {
        return [
            [['name'], 'required'],
            [['value'], 'string'],
            [['name'], 'string', 'max' => 128],
            [['updated_at'], 'integer'],
        ];
    }

    public static function get(string $name, ?string $default = null): ?string
    {
        $row = self::findOne(['name' => $name]);

        return $row !== null && $row->value !== null ? $row->value : $default;
    }

    public static function set(string $name, ?string $value): void
    {
        $row = self::findOne(['name' => $name]) ?? new self(['name' => $name]);
        $row->value = $value;
        $row->updated_at = time();
        $row->save(false);
    }

    public static function updatedAt(string $name): ?int
    {
        $row = self::findOne(['name' => $name]);

        return $row !== null ? (int)$row->updated_at : null;
    }
}
