<?php

declare(strict_types=1);

namespace app\modules\admin\models;

use app\models\Store;
use yii\base\Model;

final class EditStoreForm extends Model
{
    public string $name = '';
    public string $image_url = '';
    public string $website_url = '';
    public string $instagram_url = '';
    public string $facebook_url = '';
    public string $tiktok_url = '';

    private Store $store;

    public function __construct(Store $store, array $config = [])
    {
        $this->store = $store;
        $this->name = (string) $store->name;
        $this->image_url = (string) $store->image_url;
        $this->website_url = (string) $store->website_url;
        $this->instagram_url = (string) $store->instagram_url;
        $this->facebook_url = (string) $store->facebook_url;
        $this->tiktok_url = (string) $store->tiktok_url;
        parent::__construct($config);
    }

    public function rules(): array
    {
        return [
            [['name'], 'required'],
            [['name'], 'string', 'max' => 255],
            [['image_url', 'website_url', 'instagram_url', 'facebook_url', 'tiktok_url'], 'string', 'max' => 1024],
            [['image_url', 'website_url', 'instagram_url', 'facebook_url', 'tiktok_url'], 'url', 'defaultScheme' => 'https', 'skipOnEmpty' => true],
        ];
    }

    public function attributeLabels(): array
    {
        return [
            'name'          => 'Display name',
            'image_url'     => 'Image / logo URL',
            'website_url'   => 'Website URL',
            'instagram_url' => 'Instagram URL',
            'facebook_url'  => 'Facebook URL',
            'tiktok_url'    => 'TikTok URL',
        ];
    }

    public function save(): bool
    {
        if (!$this->validate()) {
            return false;
        }

        $this->store->name = $this->name;
        $this->store->image_url = $this->image_url !== '' ? $this->image_url : null;
        $this->store->website_url = $this->website_url !== '' ? $this->website_url : null;
        $this->store->instagram_url = $this->instagram_url !== '' ? $this->instagram_url : null;
        $this->store->facebook_url = $this->facebook_url !== '' ? $this->facebook_url : null;
        $this->store->tiktok_url = $this->tiktok_url !== '' ? $this->tiktok_url : null;

        if (!$this->store->save()) {
            $this->addError('name', 'Failed to save store: ' . implode('; ', $this->store->getFirstErrors()));

            return false;
        }

        return true;
    }
}
