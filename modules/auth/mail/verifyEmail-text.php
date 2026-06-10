<?php

declare(strict_types=1);

/** @var yii\web\View $this */
/** @var app\models\User $user */
/** @var string $verifyUrl */

echo "Hi {$user->username},\n\n";
echo 'Thanks for signing up at ' . Yii::$app->name . ".\n";
echo "Confirm your e-mail address to activate your account:\n\n";
echo $verifyUrl . "\n\n";
echo "This link expires in 24 hours. If you did not create this account, you can ignore this e-mail.\n";
