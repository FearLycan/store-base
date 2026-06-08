<?php

declare(strict_types=1);

namespace app\models;

use yii\behaviors\TimestampBehavior;
use yii\db\ActiveQuery;
use yii\db\ActiveRecord;
use yii\helpers\Inflector;

/**
 * @property int $id
 * @property string $external_category_id
 * @property int|null $parent_id
 * @property string $name
 * @property string|null $slug
 * @property int $level
 * @property int $created_at
 * @property int $updated_at
 *
 * @property Category|null $parent
 */
class Category extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%category}}';
    }

    public function behaviors(): array
    {
        return [TimestampBehavior::class];
    }

    public function rules(): array
    {
        return [
            [['external_category_id', 'name'], 'required'],
            [['external_category_id'], 'unique'],
            [['parent_id', 'level'], 'integer'],
            [['external_category_id'], 'string', 'max' => 64],
            [['name', 'slug'], 'string', 'max' => 255],
        ];
    }

    public function getParent(): ActiveQuery
    {
        return $this->hasOne(self::class, ['id' => 'parent_id']);
    }

    /**
     * Upsert one category by its external id, returning the persisted row.
     */
    public static function upsert(string $externalId, string $name, int $level, ?int $parentId): self
    {
        $category = self::findOne(['external_category_id' => $externalId]) ?? new self();
        $category->external_category_id = $externalId;
        $category->name = $name !== '' ? $name : ('Category ' . $externalId);
        $category->level = $level;
        $category->parent_id = $parentId;
        if ($category->slug === null || $category->slug === '') {
            $category->slug = Inflector::slug($category->name);
        }
        $category->save();

        return $category;
    }

    /**
     * Resolve the leaf category id from API fields. Returns the second-level id when present,
     * else the first-level id, else null. Upserts the hierarchy as a side effect.
     */
    public static function resolveLeafFromApi(array $core): ?int
    {
        $l1Id   = isset($core['category_l1_id']) ? trim((string)$core['category_l1_id']) : '';
        $l1Name = isset($core['category_l1_name']) ? (string)$core['category_l1_name'] : '';
        $l2Id   = isset($core['category_l2_id']) ? trim((string)$core['category_l2_id']) : '';
        $l2Name = isset($core['category_l2_name']) ? (string)$core['category_l2_name'] : '';

        if ($l1Id === '' && $l2Id === '') {
            return null;
        }

        $parentId = null;
        if ($l1Id !== '') {
            $parentId = self::upsert($l1Id, $l1Name, 1, null)->id;
        }
        if ($l2Id !== '') {
            return self::upsert($l2Id, $l2Name, 2, $parentId)->id;
        }

        return $parentId;
    }
}
