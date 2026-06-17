<?php

declare(strict_types=1);

namespace app\components\llm;

use RuntimeException;
use Yii;

/**
 * Builds the {@see LlmClient} backend selected by the `llm.provider` param ('ollama' | 'nvidia').
 */
final class LlmClientFactory
{
    public static function default(): LlmClient
    {
        return self::make((string)(Yii::$app->params['llm.provider'] ?? 'ollama'));
    }

    public static function make(string $provider): LlmClient
    {
        return match (strtolower(trim($provider))) {
            'ollama' => new OllamaClient(),
            'nvidia' => new NvidiaClient(),
            default  => throw new RuntimeException("Unknown LLM provider: {$provider}"),
        };
    }
}
