<?php

declare(strict_types=1);

namespace app\modules\admin\models;

use app\components\aliexpress\AliExpressLinkResolver;
use app\models\Store;
use yii\base\Model;

final class AddStoreForm extends Model
{
    public string $url = '';
    public string $name = '';

    public function rules(): array
    {
        return [
            [['url'], 'required'],
            [['url'], 'url', 'defaultScheme' => 'https'],
            [['name'], 'string', 'max' => 255],
        ];
    }

    public function attributeLabels(): array
    {
        return ['url' => 'Store URL', 'name' => 'Display name (optional)'];
    }

    public function save(): ?Store
    {
        if (!$this->validate()) {
            return null;
        }

        $externalId = (new AliExpressLinkResolver())->extractStoreId($this->url);
        if ($externalId === null) {
            $this->addError('url', 'Could not extract a store id from this URL.');

            return null;
        }

        $store = Store::findOne(['external_store_id' => $externalId]) ?? new Store();
        $store->external_store_id = $externalId;
        $store->url = $this->url;
        $store->name = $this->name !== '' ? $this->name : ('AliExpress Store ' . $externalId);
        if (!$store->save()) {
            $this->addError('url', 'Failed to save store: ' . implode('; ', $store->getFirstErrors()));

            return null;
        }

        return $store;
    }
}
