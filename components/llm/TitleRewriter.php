<?php

declare(strict_types=1);

namespace app\components\llm;

use RuntimeException;

/**
 * Turns a keyword-stuffed marketplace title into a short, natural, human-sounding product name
 * via the Ollama Cloud LLM. Output is sanitised; callers should fall back to {@see self::fallback()}
 * when the LLM is unavailable so a product can still be published.
 */
final class TitleRewriter
{
    /** Hard cap on the rewritten name; anything longer is treated as a bad response. */
    private const MAX_LENGTH = 120;

    private const PROMPT = <<<PROMPT
You rewrite messy e-commerce product titles into short, natural, human-sounding product names.

Rules:
- Output ONLY the rewritten title. No quotes, no explanation, no "Output:".
- 3 to 8 words, Title Case.
- Start with the product type or its main descriptor. NEVER start the title with "For".
- Keep the essential product type and key attributes (material, key feature, target user).
- Remove the seller's brand / store name, including ALL-CAPS vendor names like "LUXUSTEEL", "BISAER", "ZORCVENS".
- Remove marketing noise: years (2024), "New", "Hot Sale", "Free Shipping", size ranges (S-5XL), voltages, ml/size lists, model/SKU codes (e.g. B-ABLM), and long lists of compatible brands/devices.
- If device compatibility is essential, put it at the END as "... for <Device>", never at the start.
- Keep it in English.

Examples:
Input: 2024 New Fashion Men Casual Cotton Long Sleeve Slim Fit Business Shirt Plus Size S-5XL Breathable Tops
Output: Men's Slim-Fit Cotton Business Shirt

Input: LUXUSTEEL Men's Simple Hoops Earrings Black Color Stainless Steel Clip Earring for Men Women Rock Hiphop Circle Ear 2 Pieces
Output: Men's Black Stainless Steel Hoop Earrings

Input: For 14 Ultra Photo Kit Phone Case Filter Accessories B-ABLM
Output: Photo Filter Phone Case Kit for 14 Ultra

Now rewrite this one:
Input: %s
Output:
PROMPT;

    private readonly LlmClient $client;

    public function __construct(?LlmClient $client = null)
    {
        $this->client = $client ?? LlmClientFactory::default();
    }

    /**
     * @throws RuntimeException when the LLM fails or returns something unusable.
     */
    public function rewrite(string $rawTitle): string
    {
        $rawTitle = trim($rawTitle);
        if ($rawTitle === '') {
            throw new RuntimeException('Cannot rewrite an empty title.');
        }

        $raw = $this->client->generate(sprintf(self::PROMPT, $rawTitle));
        $clean = $this->sanitize($raw);

        if ($clean === '' || mb_strlen($clean) > self::MAX_LENGTH) {
            throw new RuntimeException('LLM produced an unusable title: ' . $raw);
        }

        return $clean;
    }

    /** Cheap, offline cleanup used when the LLM is unavailable, so the product still publishes. */
    public static function fallback(string $rawTitle): string
    {
        $title = trim(preg_replace('/\s+/', ' ', $rawTitle) ?? $rawTitle);
        if (mb_strlen($title) <= 70) {
            return $title;
        }
        // Trim to the last word boundary within the limit.
        $cut = mb_substr($title, 0, 70);
        $lastSpace = mb_strrpos($cut, ' ');
        return rtrim($lastSpace !== false ? mb_substr($cut, 0, $lastSpace) : $cut, " ,.-");
    }

    private function sanitize(string $text): string
    {
        // Models sometimes prepend "Output:" or wrap in quotes / add a trailing explanation line.
        $line = trim(strtok($text, "\n") ?: $text);
        $line = preg_replace('/^(output|title)\s*:\s*/i', '', $line) ?? $line;
        $line = trim($line, " \t\"'`");
        return trim(preg_replace('/\s+/', ' ', $line) ?? $line);
    }
}
