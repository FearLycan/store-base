<?php

declare(strict_types=1);

namespace app\components;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;

/**
 * Turns the raw AliExpress "detail" HTML stored in Product::$description into a
 * clean, structured payload the storefront can present on its own terms.
 *
 * The source HTML is essentially a vendor-pasted blob: a long vertical stack of
 * wide marketing graphics (infographics/lookbooks on ae01.alicdn.com), with the
 * occasional block of spec text wrapped in abused <h2> tags, plus junk we never
 * want to render — cross-sell <a> links to *other* AliExpress items, <kse:widget>
 * placeholders, empty absolutely-positioned decorators, and inline styles.
 *
 * parse() returns:
 *   - highlights: string[]  clean spec/benefit lines (· bullets, B2B & CTA spam removed)
 *   - images:     array{url:string,w:?int,h:?int}[]  in document order, de-duped
 *                 against the main gallery, with cross-sell-linked and spacer images dropped
 */
final class ProductDescription
{
    /** Lines that are vendor cross-sell CTAs or B2B spam — useless to a retail buyer. */
    private const JUNK_TEXT = [
        '~click .*(photo|below|order|here)~i',
        '~\b(buyer shows?|hot \w+ recommend)\b~i',
        '~\b(dropshipping|wholesale|oem)\b~i',
        '~we are factory~i',
    ];

    /**
     * @param array<int,string> $excludeUrls Image URLs already shown elsewhere (main gallery).
     * @return array{highlights:array<int,string>, images:array<int,array{url:string,w:?int,h:?int}>}
     */
    public static function parse(?string $html, array $excludeUrls = []): array
    {
        $empty = ['highlights' => [], 'images' => []];
        if ($html === null || trim($html) === '') {
            return $empty;
        }

        $doc = new DOMDocument();
        $prev = libxml_use_internal_errors(true);
        // The XML pragma keeps UTF-8 intact; LIBXML_* silences the custom-tag noise.
        $doc->loadHTML('<?xml encoding="utf-8" ?>' . $html, LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();
        libxml_use_internal_errors($prev);

        $xpath = new DOMXPath($doc);

        $excludeKeys = [];
        foreach ($excludeUrls as $url) {
            $key = self::imageKey((string)$url);
            if ($key !== null) {
                $excludeKeys[$key] = true;
            }
        }

        $images = self::collectImages($xpath, $excludeKeys);
        $highlights = self::collectText($xpath);

        return ['highlights' => $highlights, 'images' => $images];
    }

    /**
     * @param array<string,true> $excludeKeys
     * @return array<int,array{url:string,w:?int,h:?int}>
     */
    private static function collectImages(DOMXPath $xpath, array $excludeKeys): array
    {
        $images = [];
        $seen = $excludeKeys;

        foreach ($xpath->query('//img') as $img) {
            if (!$img instanceof DOMElement) {
                continue;
            }
            // Drop images wrapped in a link — on AliExpress these are always
            // cross-sells pointing at a *different* item, i.e. competitor ads.
            if (self::hasAncestor($img, 'a')) {
                continue;
            }

            $src = trim($img->getAttribute('src'));
            if ($src === '' || str_starts_with($src, 'data:')) {
                continue;
            }
            $src = self::normalizeUrl($src);
            if ($src === null) {
                continue;
            }

            $key = self::imageKey($src);
            if ($key !== null && isset($seen[$key])) {
                continue;
            }

            $w = self::intAttr($img, 'width');
            $h = self::intAttr($img, 'height');
            // Spacer / tracking pixels and slivers carry no content.
            if (($w !== null && $w > 0 && $w < 60) || ($h !== null && $h > 0 && $h < 60)) {
                continue;
            }

            if ($key !== null) {
                $seen[$key] = true;
            }
            $images[] = ['url' => $src, 'w' => $w, 'h' => $h];
        }

        return $images;
    }

    /** @return array<int,string> */
    private static function collectText(DOMXPath $xpath): array
    {
        $lines = [];
        $seen = [];

        // Block-level text holders. Querying these (rather than walking every node)
        // keeps each spec line intact while ignoring layout wrappers.
        foreach ($xpath->query('//h1|//h2|//h3|//h4|//h5|//h6|//p|//li') as $node) {
            if (!$node instanceof DOMNode) {
                continue;
            }
            // A <p> that only wraps images contributes no text — skip it so we
            // don't emit blank lines for the image-only blocks.
            $text = self::cleanLine($node->textContent);
            if ($text === '') {
                continue;
            }
            if (self::isJunk($text)) {
                continue;
            }
            $dedupe = mb_strtolower($text);
            if (isset($seen[$dedupe])) {
                continue;
            }
            $seen[$dedupe] = true;
            $lines[] = $text;
        }

        return $lines;
    }

    private static function cleanLine(string $raw): string
    {
        $text = html_entity_decode($raw, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = str_replace("\u{3001}", ' ', $text);          // stray Chinese comma 、
        $text = preg_replace('~\s+~u', ' ', $text) ?? $text;
        $text = trim($text);
        // Strip leading bullet markers the vendor baked into the text.
        $text = preg_replace('~^[\x{00B7}\x{2022}\x{2219}\*\-\s]+~u', '', $text) ?? $text;

        return trim($text);
    }

    private static function isJunk(string $text): bool
    {
        if (mb_strlen($text) < 2) {
            return true;
        }
        foreach (self::JUNK_TEXT as $pattern) {
            if (preg_match($pattern, $text) === 1) {
                return true;
            }
        }

        return false;
    }

    private static function hasAncestor(DOMNode $node, string $tag): bool
    {
        for ($p = $node->parentNode; $p !== null; $p = $p->parentNode) {
            if ($p instanceof DOMElement && strtolower($p->nodeName) === $tag) {
                return true;
            }
        }

        return false;
    }

    private static function intAttr(DOMElement $el, string $name): ?int
    {
        $val = trim($el->getAttribute($name));
        if ($val === '' || !preg_match('~^\d+~', $val, $m)) {
            return null;
        }

        return (int)$m[0];
    }

    private static function normalizeUrl(string $url): ?string
    {
        $url = trim($url);
        if (str_starts_with($url, '//')) {
            $url = 'https:' . $url;
        }
        if (!preg_match('~^https?://~i', $url)) {
            return null;
        }

        return $url;
    }

    /** Identity key for an alicdn image, ignoring size suffixes/query so resized dupes collapse. */
    private static function imageKey(string $url): ?string
    {
        if (preg_match('~/kf/([A-Za-z0-9]+)~', $url, $m) === 1) {
            return strtolower($m[1]);
        }
        $path = parse_url($url, PHP_URL_PATH);
        if (!is_string($path) || $path === '') {
            return null;
        }
        // Fallback: filename without the _NxN resize suffix.
        $base = preg_replace('~_\d+x\d+.*$~', '', basename($path));

        return $base !== '' ? strtolower($base) : null;
    }
}
