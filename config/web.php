<?php

$params = require __DIR__ . '/params.php';
$db = require __DIR__ . '/db.php';

$config = [
    'id'           => 'basic',
    'basePath'     => dirname(__DIR__),
    'defaultRoute' => 'catalog/index',
    'bootstrap'    => ['log'],
    'container'    => [
        'singletons' => [
            \yii\mail\MailerInterface::class => [
                'class'            => \yii\symfonymailer\Mailer::class,
                // send all mails to a file by default.
                'useFileTransport' => true,
                'viewPath'         => '@app/mail',
            ],
        ],
    ],
    'aliases'      => [
        '@bower' => '@vendor/bower-asset',
        '@npm'   => '@vendor/npm-asset',
    ],
    'components'   => [
        'request'      => [
            // !!! insert a secret key in the following (if it is empty) - this is required by cookie validation
            'cookieValidationKey' => 'pgFNd2npjk3TyQc7BgZODsK6M6WtqDL7',
        ],
        'assetManager' => [
            'appendTimestamp' => true,
        ],
        'cache'        => [
            'class' => \yii\caching\FileCache::class,
        ],
        'user'         => [
            'identityClass'   => \app\models\User::class,
            'enableAutoLogin' => true,
            'loginUrl'        => ['/auth/default/login'],
        ],
        'errorHandler' => [
            'errorAction' => 'catalog/error',
        ],
        'mailer'       => \yii\mail\MailerInterface::class,
        'log'          => [
            'traceLevel' => YII_DEBUG ? 3 : 0,
            'targets'    => [
                [
                    'class'  => \yii\log\FileTarget::class,
                    'levels' => ['error', 'warning'],
                ],
            ],
        ],
        'db'           => $db,
        'urlManager'   => [
            'enablePrettyUrl' => true,
            'showScriptName'  => false,
            'rules'           => [
                ''                           => 'catalog/index',
                'catalog'                    => 'catalog/all',
                'search'                     => 'catalog/search',
                'search/suggest'             => 'catalog/suggest',
                'product/<id:\d+>/images'    => 'catalog/images',
                'go/<id:\d+>'                => 'go/index',
                'aliexpress/callback'        => 'aliexpress/callback',
                'category/<slug:[a-z0-9-]+>' => 'catalog/category',
                'product/<slug:[a-z0-9-]+>'  => 'product/view',
                'login'                      => 'auth/default/login',
                'logout'                     => 'auth/default/logout',
                'signup'                     => 'auth/default/signup',
                'verify-email'               => 'auth/default/verify-email',
            ],
        ],
    ],
    'modules'      => [
        'admin' => [
            'class' => \app\modules\admin\AdminModule::class,
        ],
        'auth'  => [
            'class' => \app\modules\auth\AuthModule::class,
        ],
    ],
    'params'       => $params,
];

if (YII_ENV_DEV) {
    // configuration adjustments for 'dev' environment
    $config['bootstrap'][] = 'debug';
    $config['modules']['debug'] = [
        'class' => \yii\debug\Module::class,
        // uncomment the following to add your IP if you are not connecting from localhost.
        //'allowedIPs' => ['127.0.0.1', '::1'],
    ];

    $config['bootstrap'][] = 'gii';
    $config['modules']['gii'] = [
        'class' => \yii\gii\Module::class,
        // uncomment the following to add your IP if you are not connecting from localhost.
        //'allowedIPs' => ['127.0.0.1', '::1'],
    ];
}

// Local overrides (not committed to the repo). See web-local.php.example.
$local = __DIR__ . '/web-local.php';
if (is_file($local)) {
    $config = \yii\helpers\ArrayHelper::merge($config, require $local);
}

return $config;
