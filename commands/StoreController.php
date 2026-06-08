<?php

declare(strict_types=1);

namespace app\commands;

use app\components\aliexpress\AliExpressLinkResolver;
use app\enums\StoreStatusEnum;
use app\enums\SyncJobTypeEnum;
use app\models\Store;
use app\models\SyncJob;
use Yii;
use yii\console\Controller;
use yii\console\ExitCode;

/**
 * Store management from the console.
 * - `store/add <url>`  : register a source store and queue its first discovery.
 * - `store/discover`   : queue discovery for active stores that are due.
 */
final class StoreController extends Controller
{
    public function actionAdd(string $url, string $name = ''): int
    {
        $resolver = new AliExpressLinkResolver();
        $externalId = $resolver->extractStoreId($url);
        if ($externalId === null) {
            $this->stderr("Could not extract a store id from URL: {$url}\n");

            return ExitCode::USAGE;
        }

        $store = Store::findOne(['external_store_id' => $externalId]) ?? new Store();
        $store->external_store_id = $externalId;
        $store->url = $url;
        $store->name = $name !== '' ? $name : ('AliExpress Store ' . $externalId);
        if (!$store->save()) {
            $this->stderr('Failed to save store: ' . implode('; ', $store->getFirstErrors()) . "\n");

            return ExitCode::SOFTWARE;
        }

        SyncJob::enqueue(SyncJobTypeEnum::STORE_DISCOVERY, $store->id, null);
        $this->stdout("Store #{$store->id} ({$externalId}) saved; discovery queued.\n");

        return ExitCode::OK;
    }

    public function actionDiscover(): int
    {
        $intervalHours = (int)(Yii::$app->params['sync.discoveryIntervalHours'] ?? 6);
        $cutoff = time() - ($intervalHours * 3600);

        $stores = Store::find()
            ->where(['status' => StoreStatusEnum::ACTIVE->value])
            ->andWhere(['or', ['last_discovery_at' => null], ['<=', 'last_discovery_at', $cutoff]])
            ->all();

        $queued = 0;
        foreach ($stores as $store) {
            $alreadyQueued = SyncJob::find()
                ->where(['type' => SyncJobTypeEnum::STORE_DISCOVERY->value, 'store_id' => $store->id, 'status' => 'pending'])
                ->exists();
            if ($alreadyQueued) {
                continue;
            }
            SyncJob::enqueue(SyncJobTypeEnum::STORE_DISCOVERY, $store->id, null);
            $queued++;
            $this->stdout("Queued discovery for store #{$store->id} ({$store->name})\n");
        }

        $this->stdout("Queued {$queued} discovery job(s).\n");

        return ExitCode::OK;
    }
}
