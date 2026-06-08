<?php

namespace app\common\components\aliexpress;

use common\models\Set;

final class AliExpressOfferMatcher
{
    public function buildQueries(Set $set): array
    {
        $queries = [];
        $setNumber = trim((string)$set->number);
        $setName = trim((string)$set->name);
        $pieces = (int)$set->pieces;

        if ($setNumber !== '') {
            $queries[] = "building blocks {$setNumber}";
            $queries[] = "brick set {$setNumber}";
            $queries[] = "lego {$setNumber}";
        }

        if ($setName !== '') {
            $queries[] = "building blocks {$setName}";
            $queries[] = "moc {$setName} {$setNumber}";
        }

        if ($setNumber !== '' && $setName !== '') {
            $queries[] = "building blocks {$setNumber} {$setName}";
        }

        if ($setNumber !== '' && $pieces > 0) {
            $queries[] = "building blocks {$setNumber} {$pieces} pcs";
        }

        return array_values(array_unique(array_filter(array_map('trim', $queries), static fn(string $query): bool => $query !== '')));
    }

    public function score(Set $set, array $candidate): float
    {
        $title = $this->normalize((string)($candidate['name'] ?? ''));
        $url = $this->normalize((string)($candidate['url'] ?? ''));
        $haystack = trim($title . ' ' . $url);
        if ($haystack === '') {
            return 0.0;
        }

        $score = 0.0;
        $setNumber = $this->normalize((string)$set->number);
        if ($setNumber !== '') {
            if (str_contains($haystack, $setNumber)) {
                $score += 0.65;
            } else {
                return 0.0;
            }
        }

        $nameTokens = $this->extractTokens((string)$set->name);
        if ($nameTokens !== []) {
            $matchedTokens = 0;
            foreach ($nameTokens as $token) {
                if (str_contains($haystack, $token)) {
                    $matchedTokens++;
                }
            }

            $score += (min(1.0, $matchedTokens / max(1, count($nameTokens))) * 0.35);
        }

        return min(1.0, $score);
    }

    private function extractTokens(string $input): array
    {
        $normalized = $this->normalize($input);
        if ($normalized === '') {
            return [];
        }

        $parts = preg_split('/\s+/', $normalized) ?: [];
        $tokens = [];
        foreach ($parts as $part) {
            if ($part === '' || mb_strlen($part) < 3) {
                continue;
            }

            if (in_array($part, ['the', 'and', 'for', 'set', 'with', 'lego'], true)) {
                continue;
            }

            $tokens[] = $part;
        }

        return array_values(array_unique($tokens));
    }

    private function normalize(string $input): string
    {
        $value = mb_strtolower($input);
        $value = preg_replace('/[^a-z0-9]+/i', ' ', $value) ?? '';

        return trim($value);
    }
}
