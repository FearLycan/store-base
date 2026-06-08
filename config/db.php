<?php

$db = [
    'class' => \yii\db\Connection::class,
    // Defaults target the bundled `db` service in docker-compose.yml.
    'dsn' => 'mysql:host=db;dbname=yii2basic',
    'username' => 'yii2',
    'password' => 'yii2',
    'charset' => 'utf8mb4',

    // Schema cache options (for production environment)
    //'enableSchemaCache' => true,
    //'schemaCacheDuration' => 60,
    //'schemaCache' => 'cache',
];

// Local overrides (not committed to the repo). See db-local.php.example.
$local = __DIR__ . '/db-local.php';
if (is_file($local)) {
    $db = \yii\helpers\ArrayHelper::merge($db, require $local);
}

return $db;
