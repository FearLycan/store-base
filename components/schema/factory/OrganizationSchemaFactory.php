<?php

namespace app\components\schema\factory;

use Yii;

final class OrganizationSchemaFactory
{
    public static function fromParams(): array
    {
        $name = trim((string)(Yii::$app->params['site.name'] ?? Yii::$app->name));
        $url = trim((string)(Yii::$app->params['site.baseUrl'] ?? ''));
        $logo = trim((string)(Yii::$app->params['site.logo'] ?? ''));

        $schema = ['@type' => 'Organization', '@id' => '#organization', 'name' => $name !== '' ? $name : 'Store'];
        if ($url !== '') { $schema['url'] = $url; }
        if ($logo !== '') { $schema['logo'] = $logo; }

        return $schema;
    }
}
