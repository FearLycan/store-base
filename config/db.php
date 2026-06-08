<?php

$db = [
    'class' => \yii\db\Connection::class,
    'dsn' => 'mysql:host=localhost;dbname=yii2basic',
    'username' => 'root',
    'password' => '',
    'charset' => 'utf8',

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
