<?php

declare(strict_types=1);

namespace app\components\llm;

/**
 * A single-shot text completion backend used by {@see TitleRewriter}.
 * Implementations wrap one provider (Ollama Cloud, NVIDIA NIM, ...) behind the same call.
 */
interface LlmClient
{
    /**
     * Run one prompt and return the raw model text.
     *
     * @throws \RuntimeException on missing config, HTTP failure or empty response.
     */
    public function generate(string $prompt, float $temperature = 0.2): string;
}
