<?php

declare(strict_types=1);

/** @var yii\web\View $this */
/** @var app\models\User $user */
/** @var string $verifyUrl */

use yii\helpers\Html;

$accent = Yii::$app->params['site.accentColor'] ?? '#2563eb';
?>
<div style="font-family:-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif;max-width:520px;margin:0 auto;color:#0e1525;">
    <h1 style="font-size:22px;margin:0 0 16px;">Confirm your account</h1>

    <p style="font-size:15px;line-height:1.6;color:#3a4255;margin:0 0 14px;">
        Hi <?= Html::encode($user->username) ?>,
    </p>
    <p style="font-size:15px;line-height:1.6;color:#3a4255;margin:0 0 22px;">
        Thanks for signing up at <?= Html::encode(Yii::$app->name) ?>. Confirm your e-mail address
        to activate your account.
    </p>

    <p style="margin:0 0 26px;">
        <?= Html::a('Confirm my account', $verifyUrl, [
            'style' => "display:inline-block;background:{$accent};color:#fff;text-decoration:none;"
                . 'font-weight:700;font-size:15px;padding:13px 24px;border-radius:12px;',
        ]) ?>
    </p>

    <p style="font-size:13px;line-height:1.6;color:#687087;margin:0 0 6px;">
        If the button does not work, copy and paste this link into your browser:
    </p>
    <p style="font-size:13px;line-height:1.6;word-break:break-all;margin:0 0 22px;">
        <?= Html::a(Html::encode($verifyUrl), $verifyUrl, ['style' => "color:{$accent};"]) ?>
    </p>

    <p style="font-size:12px;color:#9aa1b2;margin:0;border-top:1px solid #e6e8ee;padding-top:14px;">
        This link expires in 24 hours. If you did not create this account, you can ignore this e-mail.
    </p>
</div>
