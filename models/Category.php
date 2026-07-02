<?php

declare(strict_types=1);

namespace app\models;

use app\enums\CategoryStatusEnum;
use yii\behaviors\SluggableBehavior;
use yii\behaviors\TimestampBehavior;
use yii\db\ActiveQuery;
use yii\db\ActiveRecord;

/**
 * @property int $id
 * @property string $external_category_id
 * @property int|null $parent_id
 * @property string $name
 * @property string|null $slug
 * @property string|null $image_url
 * @property string $status
 * @property string|null $intro_html
 * @property array|null $faq_json
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
        return [
            TimestampBehavior::class,
            'sluggable' => [
                'class' => SluggableBehavior::class,
                'attribute' => 'name',
                'slugAttribute' => 'slug',
                'ensureUnique' => true,
                'immutable' => true,
            ],
        ];
    }

    public function rules(): array
    {
        return [
            [['external_category_id', 'name'], 'required'],
            [['external_category_id'], 'unique'],
            [['parent_id', 'level'], 'integer'],
            [['external_category_id'], 'string', 'max' => 64],
            [['name', 'slug'], 'string', 'max' => 255],
            [['image_url'], 'string', 'max' => 1024],
            [['status'], 'string', 'max' => 16],
            [['status'], 'default', 'value' => CategoryStatusEnum::ACTIVE->value],
            [['intro_html'], 'string'],
            [['faq_json'], 'safe'],
        ];
    }

    public function getParent(): ActiveQuery
    {
        return $this->hasOne(self::class, ['id' => 'parent_id']);
    }

    public function isActive(): bool
    {
        return $this->status === CategoryStatusEnum::ACTIVE->value;
    }

    /**
     * Ids of every category hidden from the storefront: each inactive category
     * plus its whole descendant subtree (so an inactive parent takes its
     * children and grandchildren down with it, regardless of their own status).
     * Memoised per request — the storefront calls it on nearly every page.
     *
     * @return int[]
     */
    public static function hiddenIds(): array
    {
        static $cache = null;
        if ($cache !== null) {
            return $cache;
        }

        $rows = self::find()->select(['id', 'parent_id', 'status'])->asArray()->all();
        $childrenOf = [];
        $seeds = [];
        foreach ($rows as $row) {
            $id = (int) $row['id'];
            if ($row['parent_id'] !== null) {
                $childrenOf[(int) $row['parent_id']][] = $id;
            }
            if ($row['status'] !== CategoryStatusEnum::ACTIVE->value) {
                $seeds[] = $id;
            }
        }

        $hidden = [];
        $stack = $seeds;
        while ($stack !== []) {
            $id = array_pop($stack);
            if (isset($hidden[$id])) {
                continue;
            }
            $hidden[$id] = true;
            foreach ($childrenOf[$id] ?? [] as $child) {
                $stack[] = $child;
            }
        }

        return $cache = array_map('intval', array_keys($hidden));
    }

    /**
     * Add "not among the hidden subtree" to a category query, so storefront
     * navigation never surfaces an inactive category (or a child of one).
     */
    public static function excludeHidden(ActiveQuery $query, string $idColumn = 'id'): ActiveQuery
    {
        $hidden = self::hiddenIds();
        if ($hidden !== []) {
            $query->andWhere(['not in', $idColumn, $hidden]);
        }

        return $query;
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

    /**
     * Upsert a product-type leaf derived from the DS "Item Type" attribute (e.g. "Earrings", "Rings")
     * as a child of the affiliate-resolved category, returning its id. All products of the same type
     * fold into one node via a synthetic `itype:<slug>` external id, so re-imports and other stores
     * reuse it. Returns null when the type is blank — callers fall back to the affiliate leaf.
     */
    public static function resolveItemTypeChild(?int $parentId, ?string $itemType): ?int
    {
        $name = self::normalizeTypeName((string)$itemType);
        if ($name === '') {
            return null;
        }

        $parent = $parentId !== null ? self::findOne($parentId) : null;
        $level = ($parent->level ?? 0) + 1;

        return self::upsert('itype:' . self::slugifyType($name), $name, $level, $parent->id ?? null)->id;
    }

    /** Trim, collapse whitespace, Title Case — so "hoop earrings"/"EARRINGS" normalise to one label. */
    private static function normalizeTypeName(string $raw): string
    {
        $clean = trim((string)preg_replace('/\s+/', ' ', $raw));

        return $clean === '' ? '' : mb_convert_case($clean, MB_CASE_TITLE, 'UTF-8');
    }

    private static function slugifyType(string $name): string
    {
        return trim((string)preg_replace('/[^a-z0-9]+/', '-', mb_strtolower($name, 'UTF-8')), '-');
    }
}
