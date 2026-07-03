<?php

declare(strict_types=1);

namespace app\modules\admin\models;

use app\components\aliexpress\AliExpressLinkResolver;
use app\enums\SyncJobTypeEnum;
use app\models\Product;
use app\models\Store;
use app\models\SyncJob;
use yii\base\Model;

/**
 * Manual product import: paste one or more AliExpress product URLs / IDs to queue them
 * for a chosen store. A stopgap while automated store enumeration is blocked by anti-bot.
 */
final class ImportProductForm extends Model
{
    public int|string|null $store_id = null;
    public string $urls = '';

    public function rules(): array
    {
        return [
            [['store_id', 'urls'], 'required'],
            [['store_id'], 'integer'],
            [['store_id'], 'exist', 'targetClass' => Store::class, 'targetAttribute' => 'id'],
            [['urls'], 'string'],
        ];
    }

    public function attributeLabels(): array
    {
        return ['store_id' => 'Store', 'urls' => 'Product URLs or IDs (one per line)'];
    }

    /** @return int number of jobs queued */
    public function save(): int
    {
        if (!$this->validate()) {
            return 0;
        }

        $resolver = new AliExpressLinkResolver();
        $storeId = (int)$this->store_id;
        $queued = 0;
        $seen = [];

        foreach (preg_split('~\r\n|\r|\n~', $this->urls) ?: [] as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            $externalId = preg_match('~^\d{6,}$~', $line) === 1 ? $line : $resolver->extractItemId($line);
            if ($externalId === null) {
                continue;
            }
            // De-dupe within this paste (same link twice on two lines).
            if (isset($seen[$externalId])) {
                continue;
            }
            $seen[$externalId] = true;
            // Already imported for this store.
            if (Product::find()->where(['store_id' => $storeId, 'external_id' => $externalId])->exists()) {
                continue;
            }
            // Already queued and not yet processed (re-paste before the worker ran).
            $pending = SyncJob::find()
                ->where(['type' => SyncJobTypeEnum::PRODUCT_DETAIL->value, 'store_id' => $storeId])
                ->andWhere(['in', 'status', ['pending', 'processing']])
                ->andWhere(['like', 'payload_json', $externalId])
                ->exists();
            if ($pending) {
                continue;
            }

            // trusted=true → the importer skips the seller guard: a manual paste is an explicit choice.
            SyncJob::enqueue(SyncJobTypeEnum::PRODUCT_DETAIL, $storeId, null, ['external_id' => $externalId, 'trusted' => true]);
            $queued++;
        }

        return $queued;
    }
}
