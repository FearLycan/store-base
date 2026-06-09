<?php

namespace app\components\schema\factory;

use app\models\Product;
use Yii;
use yii\data\ActiveDataProvider;
use yii\helpers\Url;

final class ItemListSchemaFactory
{
    public static function fromDataProvider(ActiveDataProvider $dataProvider, string $listName = 'Products'): array
    {
        $models = $dataProvider->getModels();
        $pagination = $dataProvider->getPagination();
        $page = $pagination !== false ? (int)$pagination->getPage() : 0;
        $pageSize = $pagination !== false ? (int)$pagination->getPageSize() : count($models);
        $position = ($page * $pageSize) + 1;

        $items = [];
        foreach ($models as $model) {
            if (!$model instanceof Product) {
                continue;
            }
            $items[] = [
                '@type'    => 'ListItem',
                'position' => $position++,
                'url'      => Url::to(['/product/view', 'slug' => $model->slug], true),
                'name'     => trim((string)$model->title) !== '' ? (string)$model->title : 'Product',
            ];
        }

        return [
            '@type'           => 'ItemList',
            '@id'             => '#product-list',
            'name'            => $listName,
            'url'             => Yii::$app->request->absoluteUrl,
            'itemListOrder'   => 'https://schema.org/ItemListOrderAscending',
            'numberOfItems'   => (int)$dataProvider->getTotalCount(),
            'itemListElement' => $items,
        ];
    }
}
