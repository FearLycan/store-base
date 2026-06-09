<?php

namespace app\components\schema\builder;

use app\components\schema\factory\BreadcrumbListSchemaFactory;
use app\components\schema\factory\ItemListSchemaFactory;
use yii\data\ActiveDataProvider;

final class ListingPageSchemaBuilder
{
    public static function build(ActiveDataProvider $dataProvider, array $links, array $homeLink, string $currentTitle, string $listName): array
    {
        return [
            BreadcrumbListSchemaFactory::fromView($links, $homeLink, $currentTitle),
            ItemListSchemaFactory::fromDataProvider($dataProvider, $listName),
        ];
    }
}
