<?php

$params = [
    'adminEmail' => 'admin@example.com',
    'senderEmail' => 'noreply@example.com',
    'senderName' => 'Example.com mailer',

    // --- AliExpress Affiliate Open API ---
    'aliexpress.apiBaseUrl'     => 'https://api-sg.aliexpress.com/sync',
    'aliexpress.appKey'         => '',   // set in params-local.php
    'aliexpress.appSecret'      => '',   // set in params-local.php
    'aliexpress.trackingId'     => '',   // set in params-local.php (required for affiliate links)
    'aliexpress.dropshipping.appKey'         => '',   // set in params-local.php
    'aliexpress.dropshipping.appSecret'      => '',   // set in params-local.php
    'aliexpress.dropshipping.callbackUrl'    => '',   // set in params-local.php (OAuth redirect_uri, must match the AE console; e.g. https://yourdomain/admin/setting/ds-callback)
    'aliexpress.targetCurrency' => 'USD',
    'aliexpress.targetLanguage' => 'EN',
    'aliexpress.shipToCountry'  => 'US',

    // --- AliExpress mtop (unofficial scraping for listing/detail/reviews) ---
    'aliexpress.mtop.appKey'  => '12574478',
    'aliexpress.mtop.lang'    => 'en_US',
    'aliexpress.mtop.country' => 'US',

    // --- LLM backend for product-title rewriting: 'ollama' | 'nvidia' ---
    'llm.provider' => 'ollama',

    // --- Ollama Cloud (LLM product-title rewriting) ---
    'ollama.endpoint' => 'https://ollama.com/api/generate',
    'ollama.model'    => 'gpt-oss:120b-cloud',
    'ollama.apiKey'   => '',   // set in params-local.php
    'ollama.timeout'  => 60,

    // --- NVIDIA NIM (OpenAI-compatible chat completions) ---
    'nvidia.endpoint' => 'https://integrate.api.nvidia.com/v1/chat/completions',
    'nvidia.model'    => 'nvidia/nemotron-3-super-120b-a12b',
    'nvidia.apiKey'   => '',   // set in params-local.php
    'nvidia.timeout'  => 60,

    // --- Sync cadence / batching ---
    'sync.discoveryIntervalHours'    => 6,
    'sync.priceRefreshIntervalDays'  => 1,
    'sync.reviewRefreshIntervalDays' => 3,
    'sync.batchSize'                 => 20,
    'sync.maxAttempts'               => 5,

    // --- Site / niche identity (per deployment) ---
    'site.name'  => 'Store Base',
    'site.niche' => 'general',

    // --- Branding (per deployment; override in params-local) ---
    'site.baseUrl'      => 'https://example.com', // override in params-local
    'site.logo'         => '',
    'site.accentColor'  => '#2563eb',
    'site.tagline'      => 'Curated finds, updated daily.',
    'site.footer'       => '',
];

// Local overrides (not committed to the repo). See params-local.php.example.
$local = __DIR__ . '/params-local.php';
if (is_file($local)) {
    $params = \yii\helpers\ArrayHelper::merge($params, require $local);
}

return $params;
