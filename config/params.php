<?php

$params = [
    'adminEmail'  => 'admin@example.com',
    'senderEmail' => 'noreply@example.com',
    'senderName'  => 'Example.com mailer',

    'gtag'                                => '',
    'pagead2'                             => '',
    'leadTag'                             => '',
    'impactTag'                           => '',
    'smart-links'                         => [],

    // --- AliExpress Affiliate Open API ---
    'aliexpress.apiBaseUrl'               => 'https://api-sg.aliexpress.com/sync',
    'aliexpress.appKey'                   => '',   // set in params-local.php
    'aliexpress.appSecret'                => '',   // set in params-local.php
    'aliexpress.trackingId'               => '',   // set in params-local.php (required for affiliate links)
    'aliexpress.dropshipping.appKey'      => '',   // set in params-local.php
    'aliexpress.dropshipping.appSecret'   => '',   // set in params-local.php
    'aliexpress.dropshipping.callbackUrl' => '',   // set in params-local.php (OAuth redirect_uri, must match the AE console; e.g. https://yourdomain/admin/setting/ds-callback)
    'aliexpress.targetCurrency'           => 'USD',
    'aliexpress.targetLanguage'           => 'EN',
    'aliexpress.shipToCountry'            => 'US',

    // --- Shared Redis (cross-deployment coordination of the DS OAuth token) ---
    // All deployments on the host point at the SAME socket + password + namespace so they share one
    // token: exactly one instance refreshes it, the rest read it. Empty socket = feature off (each app
    // keeps its own token in the Setting table, i.e. the legacy behaviour). See AliExpressDsClient /
    // DsTokenStore, and cron.txt → "Redis (współdzielony token)".
    'redis.socket'                        => '',          // e.g. /usr/home/LOGIN/domains/DOMAIN/redis.sock — set in params-local
    'redis.password'                      => '',          // set in params-local; REQUIRED on shared hosting (else other users read your token)
    'redis.namespace'                     => 'snagloft',  // MUST be identical across every deployment that shares the token

    // --- AliExpress mtop (unofficial scraping for listing/detail/reviews) ---
    'aliexpress.mtop.appKey'              => '12574478',
    'aliexpress.mtop.lang'                => 'en_US',
    'aliexpress.mtop.country'             => 'US',

    // --- LLM backend for product-title rewriting: 'ollama' | 'nvidia' ---
    'llm.provider'                        => 'ollama',

    // --- Ollama Cloud (LLM product-title rewriting) ---
    'ollama.endpoint'                     => 'https://ollama.com/api/generate',
    'ollama.model'                        => 'gpt-oss:120b-cloud',
    'ollama.apiKey'                       => '',   // set in params-local.php
    'ollama.timeout'                      => 60,

    // --- NVIDIA NIM (OpenAI-compatible chat completions) ---
    'nvidia.endpoint'                     => 'https://integrate.api.nvidia.com/v1/chat/completions',
    'nvidia.model'                        => 'nvidia/nemotron-3-super-120b-a12b',
    'nvidia.apiKey'                       => '',   // set in params-local.php
    'nvidia.timeout'                      => 60,

    // --- Sync cadence / batching ---
    'sync.discoveryIntervalHours'         => 6,
    'sync.priceRefreshIntervalDays'       => 1,
    'sync.reviewRefreshIntervalDays'      => 3,
    'sync.maxAttempts'                    => 5,
    // sync/process (no explicit limit) drains the queue until empty or this budget elapses. The
    // MysqlMutex serialises runs, so a run may safely span many cron ticks (overlaps just skip).
    // ~55 min keeps one run well inside an hour while staying near-continuous under a 5-min cron.
    'sync.timeBudgetSeconds'              => 3300,

    // --- Site / niche identity (per deployment) ---
    'site.name'                           => 'Store Base',
    'site.niche'                          => 'general',

    // --- Branding (per deployment; override in params-local) ---
    'site.baseUrl'                        => 'https://example.com', // override in params-local
    'site.logo'                           => '',                    // custom logo image; empty = snagloft brand logo
    'site.accentColor'                    => '',                    // empty = resolved from brand.palette by site.niche
    'site.tagline'                        => 'Curated finds, updated daily.',
    'site.footer'                         => '',

    // --- snagloft brand (shared across every deployment; hub = snagloft.com) ---
    'brand.name'                          => 'snagloft',
    'brand.hubUrl'                        => 'https://snagloft.com',
    'brand.slogan'                        => 'A loft full of the good stuff. Sorted, not dumped.',
    'brand.ink'                           => '#1F1E1C',
    'brand.cream'                         => '#F4EFE6',
    // Per-niche accent, mirroring the hub palette (snagloft.com/files/snagloft.css).
    'brand.palette'                       => [
        'jewelry'     => '#E0592E', // coral
        'watches'     => '#12876A', // teal
        'glasses'     => '#12876A', // teal
        'shoes'       => '#BA7517', // amber
        'electronics' => '#185FA5', // blue
        'lego'        => '#C8322B', // brick red
        'games'       => '#6D45C9', // violet
    ],
    'brand.defaultAccent'                 => '#E0592E', // hub coral
];

// Local overrides (not committed to the repo). See params-local.php.example.
$local = __DIR__ . '/params-local.php';
if (is_file($local)) {
    $params = \yii\helpers\ArrayHelper::merge($params, require $local);
}

return $params;
