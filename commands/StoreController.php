<?php

declare(strict_types=1);

namespace app\commands;

use app\components\aliexpress\AliExpressLinkResolver;
use app\models\Store;
use yii\console\Controller;
use yii\console\ExitCode;

/**
 * Store management from the console.
 * - `store/add <url>`  : register a source store.
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

        $this->stdout("Store #{$store->id} ({$externalId}) saved.\n");

        return ExitCode::OK;
    }
}
