<?php

namespace app\components\schema\factory;

final class FaqSchemaFactory
{
    /**
     * Build a FAQPage node from a list of {q,a} pairs. Returns [] when there are
     * no usable pairs, so JsonLdRenderer drops it from the @graph.
     *
     * @param array<int, array{q?: string, a?: string}> $pairs
     * @return array<string, mixed>
     */
    public static function fromPairs(array $pairs): array
    {
        $questions = [];
        foreach ($pairs as $pair) {
            $q = trim((string) ($pair['q'] ?? ''));
            $a = trim((string) ($pair['a'] ?? ''));
            if ($q === '' || $a === '') {
                continue;
            }
            $questions[] = [
                '@type' => 'Question',
                'name' => $q,
                'acceptedAnswer' => ['@type' => 'Answer', 'text' => $a],
            ];
        }

        if ($questions === []) {
            return [];
        }

        return ['@type' => 'FAQPage', 'mainEntity' => $questions];
    }
}
