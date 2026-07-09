<?php

declare(strict_types=1);

namespace app\models;

use yii\behaviors\TimestampBehavior;
use yii\db\ActiveQuery;
use yii\db\ActiveRecord;

/**
 * Admin-managed hero banner shown in the store page slider. image_url is a
 * root-relative /uploads/banners/… path for uploaded files, or any external URL.
 *
 * @property int $id
 * @property int $store_id
 * @property string $image_url
 * @property string|null $link_url
 * @property string|null $headline
 * @property string|null $subheadline
 * @property int $sort_order
 * @property string $status
 * @property int $created_at
 * @property int $updated_at
 *
 * @property Store $store
 */
class StoreBanner extends ActiveRecord
{
    public const STATUS_ACTIVE = 'active';
    public const STATUS_HIDDEN = 'hidden';

    public static function tableName(): string
    {
        return '{{%store_banner}}';
    }

    public function behaviors(): array
    {
        return [TimestampBehavior::class];
    }

    public function rules(): array
    {
        return [
            [['store_id', 'image_url'], 'required'],
            [['store_id', 'sort_order'], 'integer'],
            [['image_url', 'link_url'], 'string', 'max' => 1024],
            [['headline', 'subheadline'], 'string', 'max' => 255],
            [['status'], 'in', 'range' => [self::STATUS_ACTIVE, self::STATUS_HIDDEN]],
            [['status'], 'default', 'value' => self::STATUS_ACTIVE],
            [['sort_order'], 'default', 'value' => 0],
        ];
    }

    /**
     * Active banners for a store in display order — the storefront slider source.
     *
     * @return self[]
     */
    public static function forStore(int $storeId): array
    {
        return self::find()
            ->where(['store_id' => $storeId, 'status' => self::STATUS_ACTIVE])
            ->orderBy(['sort_order' => SORT_ASC, 'id' => SORT_ASC])
            ->all();
    }

    public function getStore(): ActiveQuery
    {
        return $this->hasOne(Store::class, ['id' => 'store_id']);
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }
}
