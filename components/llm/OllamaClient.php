<?php

declare(strict_types=1);

namespace app\components\llm;

use RuntimeException;
use Yii;
use yii\httpclient\Client;

/**
 * Thin client for the Ollama Cloud generate API (https://ollama.com/api/generate).
 * Single-shot, non-streaming completions. Credentials/model come from params.
 */
final class OllamaClient implements LlmClient
{
    private Client $client;

    public function __construct(?Client $client = null)
    {
        $this->client = $client ?? new Client(['transport' => 'yii\httpclient\CurlTransport']);
    }

    /**
     * Run one prompt and return the raw model text. Low temperature by default for stable output.
     *
     * @throws RuntimeException on missing config, HTTP failure or empty response.
     */
    public function generate(string $prompt, float $temperature = 0.2): string
    {
        $endpoint = (string)(Yii::$app->params['ollama.endpoint'] ?? 'https://ollama.com/api/generate');
        $model    = (string)(Yii::$app->params['ollama.model'] ?? '');
        $apiKey   = trim((string)(Yii::$app->params['ollama.apiKey'] ?? ''));
        $timeout  = (int)(Yii::$app->params['ollama.timeout'] ?? 60);

        if ($apiKey === '' || $model === '') {
            throw new RuntimeException('Ollama is not configured (ollama.apiKey / ollama.model).');
        }

        $response = $this->client->createRequest()
            ->setMethod('POST')
            ->setUrl($endpoint)
            ->addHeaders([
                'Content-Type'  => 'application/json',
                'Authorization' => 'Bearer ' . $apiKey,
            ])
            ->setFormat(Client::FORMAT_JSON)
            ->setData([
                'model'   => $model,
                'stream'  => false,
                'options' => ['temperature' => $temperature],
                'prompt'  => $prompt,
            ])
            ->setOptions([
                'timeout' => $timeout,
            ])
            ->send();

        if (!$response->getIsOk()) {
            throw new RuntimeException('Ollama HTTP ' . $response->getStatusCode() . ': ' . $response->getContent());
        }

        $text = (string)($response->getData()['response'] ?? '');
        if (trim($text) === '') {
            throw new RuntimeException('Ollama returned an empty response.');
        }

        return $text;
    }
}
