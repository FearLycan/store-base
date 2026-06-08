<?php

$params = [
    'adminEmail' => 'admin@example.com',
    'senderEmail' => 'noreply@example.com',
    'senderName' => 'Example.com mailer',
];

// Local overrides (not committed to the repo). See params-local.php.example.
$local = __DIR__ . '/params-local.php';
if (is_file($local)) {
    $params = \yii\helpers\ArrayHelper::merge($params, require $local);
}

return $params;
