<?php

declare(strict_types=1);

/** @var yii\web\View $this */
/** @var app\modules\auth\models\SignupForm $model */

use app\widgets\Alert;
use yii\bootstrap5\ActiveForm;
use yii\helpers\Html;
use yii\helpers\Url;

$this->title = 'Create account';

$userIcon = <<<SVG
<svg class="auth-field__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
SVG;
$mailIcon = <<<SVG
<svg class="auth-field__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/></svg>
SVG;
$lockIcon = <<<SVG
<svg class="auth-field__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
SVG;
?>
<main class="auth-card">

    <?= $this->render('_brand', [
        'title' => 'Join the<br>hub.',
        'text' => 'Create an account to follow stores and keep an eye on the products you care about.',
        'points' => ['Free to get started', 'Confirm by e-mail in one click', 'Cancel any time'],
    ]) ?>

    <section class="auth-form">
        <div class="auth-form__head">
            <p class="auth-form__eyebrow">Get started</p>
            <h1 class="auth-form__title">Create account</h1>
            <p class="auth-form__sub">It takes less than a minute.</p>
        </div>

        <?= Alert::widget() ?>

        <?php $form = ActiveForm::begin([
            'id' => 'signup-form',
            'fieldConfig' => [
                'options' => ['class' => 'form-group'],
                'errorOptions' => ['class' => 'invalid-feedback'],
                'inputOptions' => ['class' => 'form-control'],
            ],
        ]); ?>

        <div class="auth-stagger">
            <?= $form->field($model, 'username', [
                'template' => "{label}<div class=\"auth-field\">{$userIcon}{input}</div>{error}",
            ])->textInput(['autofocus' => true, 'placeholder' => 'Pick a username', 'autocomplete' => 'username']) ?>

            <?= $form->field($model, 'email', [
                'template' => "{label}<div class=\"auth-field\">{$mailIcon}{input}</div>{error}",
            ])->textInput(['type' => 'email', 'placeholder' => 'you@example.com', 'autocomplete' => 'email']) ?>

            <?= $form->field($model, 'password', [
                'template' => "{label}<div class=\"auth-field\">{$lockIcon}{input}</div>{error}{hint}",
            ])->passwordInput(['placeholder' => 'At least 8 characters', 'autocomplete' => 'new-password'])
                ->hint('Use 8 characters or more.') ?>

            <?= Html::submitButton('Create account', ['class' => 'auth-submit', 'name' => 'signup-button']) ?>
        </div>

        <?php ActiveForm::end(); ?>

        <p class="auth-alt">
            Already have an account? <?= Html::a('Sign in', Url::to(['/auth/default/login']), ['class' => 'auth-link']) ?>
        </p>
    </section>
</main>
