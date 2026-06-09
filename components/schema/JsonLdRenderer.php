<?php

namespace app\components\schema;

use yii\helpers\Json;

final class JsonLdRenderer
{
    public static function render(array $nodes): string
    {
        $filteredNodes = array_values(array_filter($nodes, static fn($node): bool => is_array($node) && $node !== []));
        if ($filteredNodes === []) {
            return '';
        }

        $payload = [
            '@context' => 'https://schema.org',
            '@graph'   => $filteredNodes,
        ];

        $json = Json::encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return '<script type="application/ld+json">' . $json . '</script>';
    }
}
