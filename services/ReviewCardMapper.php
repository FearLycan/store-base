<?php

declare(strict_types=1);

namespace app\services;

use app\models\ProductReview;
use Yii;

/**
 * Turns a review (from the AE API item shape OR a stored ProductReview) into the
 * flat "card DTO" the _review-cards.php partial renders. Keeping one output shape
 * means SSR and the AJAX endpoint can share the same partial.
 *
 * Card DTO: ['name','initial','color','flag','rating','date','content','images'=>string[]]
 */
final class ReviewCardMapper
{
    private const AVATAR_COLORS = ['#fb7185', '#f59e0b', '#10b981', '#60a5fa', '#a78bfa', '#f472b6', '#2dd4bf', '#fb923c'];

    /** @param array $item AE-normalized item from AliExpressReviewScraper::fetchPage()['reviews'] */
    public static function fromApiItem(array $item): array
    {
        $images = [];
        foreach ((array)($item['images'] ?? []) as $u) {
            $u = trim((string)$u);
            if ($u !== '') {
                $images[] = $u;
            }
        }

        return self::build(
            (string)($item['author_name'] ?? ''),
            $item['reviewer_country'] ?? null,
            isset($item['rating_value']) ? (float)$item['rating_value'] : 0.0,
            isset($item['reviewed_at']) ? (int)$item['reviewed_at'] : null,
            (string)($item['content'] ?? ''),
            $images
        );
    }

    public static function fromModel(ProductReview $r): array
    {
        $images = [];
        foreach ($r->images as $ri) {
            $u = trim((string)$ri->url);
            if ($u !== '') {
                $images[] = $u;
            }
        }

        return self::build(
            (string)($r->author_name ?? ''),
            $r->reviewer_country,
            (float)$r->rating_value,
            $r->reviewed_at !== null ? (int)$r->reviewed_at : null,
            trim((string)$r->content),
            $images
        );
    }

    private static function build(string $name, ?string $country, float $rating, ?int $ts, string $content, array $images): array
    {
        $name = trim($name) !== '' ? trim($name) : 'Anonymous';
        $initial = preg_match('~[\p{L}\p{N}]~u', $name, $m) === 1 ? mb_strtoupper($m[0]) : '?';

        return [
            'name'    => $name,
            'initial' => $initial,
            'color'   => self::AVATAR_COLORS[abs(crc32($name)) % count(self::AVATAR_COLORS)],
            'flag'    => self::flagOf($country),
            'rating'  => $rating,
            'date'    => $ts ? Yii::$app->formatter->asDate($ts, 'medium') : '',
            'content' => $content,
            'images'  => $images,
        ];
    }

    /** 2-letter ISO country code -> flag emoji, else ''. */
    public static function flagOf(?string $cc): string
    {
        if ($cc === null) {
            return '';
        }
        $cc = strtoupper(trim($cc));
        if (preg_match('~^[A-Z]{2}$~', $cc) !== 1) {
            return '';
        }

        return mb_convert_encoding('&#' . (127397 + ord($cc[0])) . ';', 'UTF-8', 'HTML-ENTITIES')
            . mb_convert_encoding('&#' . (127397 + ord($cc[1])) . ';', 'UTF-8', 'HTML-ENTITIES');
    }
}
