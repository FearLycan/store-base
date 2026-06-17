<?php

declare(strict_types=1);

namespace app\components\llm;

use RuntimeException;
use Yii;
use yii\httpclient\Client;

/**
 * Thin client for NVIDIA's OpenAI-compatible chat-completions API
 * (https://integrate.api.nvidia.com/v1/chat/completions). Alternative LLM backend to
 * {@see OllamaClient}; credentials/model come from params.
 */
final class NvidiaClient implements LlmClient
{
    private Client $client;

    public function __construct(?Client $client = null)
    {
        $this->client = $client ?? new Client(['transport' => 'yii\httpclient\CurlTransport']);
    }

    public function generate(string $prompt, float $temperature = 0.2): string
    {
        $endpoint = (string)(Yii::$app->params['nvidia.endpoint'] ?? 'https://integrate.api.nvidia.com/v1/chat/completions');
        $model    = (string)(Yii::$app->params['nvidia.model'] ?? '');
        $apiKey   = trim((string)(Yii::$app->params['nvidia.apiKey'] ?? ''));
        $timeout  = (int)(Yii::$app->params['nvidia.timeout'] ?? 60);

        if ($apiKey === '' || $model === '') {
            throw new RuntimeException('NVIDIA is not configured (nvidia.apiKey / nvidia.model).');
        }

        $response = $this->client->createRequest()
            ->setMethod('POST')
            ->setUrl($endpoint)
            ->addHeaders([
                'Content-Type'  => 'application/json',
                'Accept'        => 'application/json',
                'Authorization' => 'Bearer ' . $apiKey,
            ])
            ->setFormat(Client::FORMAT_JSON)
            ->setData([
                'model'       => $model,
                'temperature' => $temperature,
                'stream'      => false,
                'messages'    => [
                    // Nemotron reasoning toggle; ignored by non-reasoning models.
                    ['role' => 'system', 'content' => 'detailed thinking off'],
                    ['role' => 'user', 'content' => $prompt],
                ],
            ])
            ->setOptions([
                'timeout' => $timeout,
            ])
            ->send();

        if (!$response->getIsOk()) {
            throw new RuntimeException('NVIDIA HTTP ' . $response->getStatusCode() . ': ' . $response->getContent());
        }

        $text = (string)($response->getData()['choices'][0]['message']['content'] ?? '');
        // Reasoning models can emit a <think>...</think> block before the answer; drop it.
        $text = preg_replace('/<think>.*?<\/think>/is', '', $text) ?? $text;

        if (trim($text) === '') {
            throw new RuntimeException('NVIDIA returned an empty response.');
        }

        return trim($text);
    }
}
