<?php

declare(strict_types=1);

namespace app\modules\admin\models;

use app\models\Store;
use yii\base\Model;

final class EditStoreForm extends Model
{
    public string $name = '';
    public string $image_url = '';

    private Store $store;

    public function __construct(Store $store, array $config = [])
    {
        $this->store = $store;
        $this->name = (string) $store->name;
        $this->image_url = (string) $store->image_url;
        parent::__construct($config);
    }

    public function rules(): array
    {
        return [
            [['name'], 'required'],
            [['name'], 'string', 'max' => 255],
            [['image_url'], 'string', 'max' => 1024],
            [['image_url'], 'url', 'defaultScheme' => 'https', 'skipOnEmpty' => true],
        ];
    }

    public function attributeLabels(): array
    {
        return [
            'name'      => 'Display name',
            'image_url' => 'Image / logo URL',
        ];
    }

    public function save(): bool
    {
        if (!$this->validate()) {
            return false;
        }

        $this->store->name = $this->name;
        $this->store->image_url = $this->image_url !== '' ? $this->image_url : null;

        if (!$this->store->save()) {
            $this->addError('name', 'Failed to save store: ' . implode('; ', $this->store->getFirstErrors()));

            return false;
        }

        return true;
    }
}
