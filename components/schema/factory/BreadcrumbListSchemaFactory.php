<?php

namespace app\components\schema\factory;

use yii\helpers\Url;

final class BreadcrumbListSchemaFactory
{
    public static function fromView(array $links, array $homeLink, string $currentTitle = ''): array
    {
        $items = [];
        $position = 1;

        self::appendItem($items, $position, $homeLink['label'] ?? '', $homeLink['url'] ?? null);

        foreach ($links as $link) {
            if (is_array($link)) {
                self::appendItem($items, $position, $link['label'] ?? '', $link['url'] ?? null);
                continue;
            }

            self::appendItem($items, $position, (string)$link, null);
        }

        $title = self::sanitizeLabel($currentTitle);
        if ($title !== '' && !self::hasSameLastLabel($items, $title) && !self::isLastItemTerminal($items)) {
            self::appendItem($items, $position, $title, null);
        }

        if ($items === []) {
            return [];
        }

        return [
            '@type' => 'BreadcrumbList',
            '@id' => '#breadcrumb-list',
            'itemListElement' => $items,
        ];
    }

    private static function appendItem(array &$items, int &$position, string $label, mixed $url): void
    {
        $sanitizedLabel = self::sanitizeLabel($label);
        if ($sanitizedLabel === '') {
            return;
        }

        if (self::hasSameLastLabel($items, $sanitizedLabel)) {
            return;
        }

        $item = [
            '@type' => 'ListItem',
            'position' => $position,
            'name' => $sanitizedLabel,
        ];

        $resolvedUrl = self::resolveUrl($url);
        if ($resolvedUrl !== null) {
            $item['item'] = $resolvedUrl;
        }

        $items[] = $item;
        $position++;
    }

    private static function resolveUrl(mixed $url): ?string
    {
        if ($url === null || $url === '') {
            return null;
        }

        return Url::to($url, true);
    }

    private static function sanitizeLabel(string $label): string
    {
        $sanitized = trim(strip_tags(html_entity_decode($label, ENT_QUOTES | ENT_HTML5)));
        $sanitized = str_replace(['®', '™'], '', $sanitized);

        return preg_replace('/\s+/', ' ', $sanitized) ?? '';
    }

    private static function hasSameLastLabel(array $items, string $label): bool
    {
        if ($items === []) {
            return false;
        }

        $lastItem = $items[array_key_last($items)];

        return isset($lastItem['name']) && (string)$lastItem['name'] === $label;
    }

    private static function isLastItemTerminal(array $items): bool
    {
        if ($items === []) {
            return false;
        }

        $lastItem = $items[array_key_last($items)];

        return !isset($lastItem['item']) || trim((string)$lastItem['item']) === '';
    }
}
