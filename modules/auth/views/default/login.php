<?php

declare(strict_types=1);

/** @var yii\web\View $this */
/** @var app\modules\auth\models\LoginForm $model */

use app\widgets\Alert;
use yii\bootstrap5\ActiveForm;
use yii\helpers\Html;
use yii\helpers\Url;

$this->title = 'Log in';

$userIcon = <<<SVG
<svg class="auth-field__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
SVG;
$lockIcon = <<<SVG
<svg class="auth-field__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
SVG;
?>
<main class="auth-card">

    <?= $this->render('_brand', [
        'title' => 'Welcome<br>back.',
        'text' => 'Sign in to manage your catalogue, stores and everything behind the storefront.',
        'points' => ['Live product & price sync', 'Curated categories', 'One hub for every store'],
    ]) ?>

    <section class="auth-form">
        <div class="auth-form__head">
            <p class="auth-form__eyebrow">Account</p>
            <h1 class="auth-form__title">Sign in</h1>
            <p class="auth-form__sub">Enter your credentials to continue.</p>
        </div>

        <?= Alert::widget() ?>

        <?php $form = ActiveForm::begin([
            'id' => 'login-form',
            'fieldConfig' => [
                'options' => ['class' => 'form-group'],
                'errorOptions' => ['class' => 'invalid-feedback'],
                'inputOptions' => ['class' => 'form-control'],
            ],
        ]); ?>

        <div class="auth-stagger">
            <?= $form->field($model, 'username', [
                'template' => "{label}<div class=\"auth-field\">{$userIcon}{input}</div>{error}",
            ])->textInput(['autofocus' => true, 'placeholder' => 'Your username', 'autocomplete' => 'username']) ?>

            <?= $form->field($model, 'password', [
                'template' => "{label}<div class=\"auth-field\">{$lockIcon}{input}</div>{error}",
            ])->passwordInput(['placeholder' => 'Your password', 'autocomplete' => 'current-password']) ?>

            <div class="auth-row">
                <?= $form->field($model, 'rememberMe', [
                    'template' => '{input} {label}',
                    'options' => ['class' => 'form-check'],
                    'labelOptions' => ['class' => 'form-check-label'],
                ])->checkbox(['class' => 'form-check-input'], false) ?>
            </div>

            <?= Html::submitButton('Sign in', ['class' => 'auth-submit', 'name' => 'login-button']) ?>
        </div>

        <?php ActiveForm::end(); ?>

        <p class="auth-alt">
            New here? <?= Html::a('Create an account', Url::to(['/auth/default/signup']), ['class' => 'auth-link']) ?>
        </p>
    </section>
</main>
