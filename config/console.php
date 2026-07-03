<?php

$params = require __DIR__ . '/params.php';
$db = require __DIR__ . '/db.php';

$config = [
    'id' => 'basic-console',
    'basePath' => dirname(__DIR__),
    'bootstrap' => ['log'],
    'controllerNamespace' => 'app\commands',
    'aliases' => [
        '@bower' => '@vendor/bower-asset',
        '@npm'   => '@vendor/npm-asset',
        '@tests' => '@app/tests',
    ],
    'components' => [
        'cache' => [
            'class' => \yii\caching\FileCache::class,
        ],
        'mutex' => [
            // DB-backed named locks (GET_LOCK) so overlapping cron runs of the same
            // command skip instead of doubling up API calls. Auto-released on exit.
            'class' => \yii\mutex\MysqlMutex::class,
        ],
        'log' => [
            // Console commands (mostly cron) each get their own log file, routed by the
            // command's class prefix — Yii::error($msg, __METHOD__) tags every entry with
            // `app\commands\<Name>Controller::action…`, which these `categories` match.
            // Everything else (uncategorised core errors) falls through to app.log.
            'targets' => array_merge(
                array_map(
                    static fn (string $id): array => [
                        'class' => \yii\log\FileTarget::class,
                        'levels' => ['error', 'warning'],
                        'categories' => ['app\\commands\\' . ucfirst($id) . 'Controller*'],
                        'logFile' => '@runtime/logs/' . $id . '.log',
                        'logVars' => [], // console has no meaningful request vars to dump
                    ],
                    ['sync', 'store', 'product', 'review', 'ds', 'sitemap', 'catalog', 'user'],
                ),
                [
                    [
                        'class' => \yii\log\FileTarget::class,
                        'levels' => ['error', 'warning'],
                        'except' => ['app\\commands\\*'],
                        'logVars' => [],
                    ],
                ],
            ),
        ],
        'db' => $db,
    ],
    'params' => $params,
    /*
    'controllerMap' => [
        'fixture' => [ // Fixture generation command line.
            'class' => 'yii\faker\FixtureController',
        ],
    ],
    */
];

if (YII_ENV_DEV) {
    // configuration adjustments for 'dev' environment
    $config['bootstrap'][] = 'gii';
    $config['modules']['gii'] = [
        'class' => \yii\gii\Module::class,
    ];
    // configuration adjustments for 'dev' environment
    // requires version `2.1.21` of yii2-debug module
    $config['bootstrap'][] = 'debug';
    $config['modules']['debug'] = [
        'class' => \yii\debug\Module::class,
        // uncomment the following to add your IP if you are not connecting from localhost.
        //'allowedIPs' => ['127.0.0.1', '::1'],
    ];
}

return $config;
