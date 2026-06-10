<?php

declare(strict_types=1);

namespace app\modules\auth\controllers;

use app\models\User;
use app\modules\auth\models\LoginForm;
use app\modules\auth\models\SignupForm;
use Yii;
use yii\filters\AccessControl;
use yii\filters\VerbFilter;
use yii\mail\MailerInterface;
use yii\web\Controller;
use yii\web\Response;

final class DefaultController extends Controller
{
    public function __construct(
        $id,
        $module,
        private readonly MailerInterface $mailer,
        $config = [],
    ) {
        parent::__construct($id, $module, $config);
    }

    public function behaviors(): array
    {
        return [
            'access' => [
                'class' => AccessControl::class,
                'rules' => [
                    [
                        'actions' => ['login', 'signup', 'verify-email'],
                        'allow' => true,
                    ],
                    [
                        'actions' => ['logout'],
                        'allow' => true,
                        'roles' => ['@'],
                    ],
                ],
            ],
            'verbs' => [
                'class' => VerbFilter::class,
                'actions' => [
                    'logout' => ['post'],
                ],
            ],
        ];
    }

    /**
     * Username / password login.
     */
    public function actionLogin(): Response|string
    {
        if (!Yii::$app->user->isGuest) {
            return $this->goHome();
        }

        $model = new LoginForm();

        if ($model->load($this->request->post()) && $model->login()) {
            return $this->goBack();
        }

        $model->password = '';

        return $this->render('login', ['model' => $model]);
    }

    /**
     * Logs the current user out.
     */
    public function actionLogout(): Response
    {
        Yii::$app->user->logout();

        return $this->goHome();
    }

    /**
     * Public sign-up. Creates an inactive account and e-mails a verification link.
     */
    public function actionSignup(): Response|string
    {
        if (!Yii::$app->user->isGuest) {
            return $this->goHome();
        }

        $model = new SignupForm();

        if ($model->load($this->request->post())) {
            $user = $model->signup();
            if ($user !== null) {
                $model->sendVerificationEmail($user, $this->mailer);
                Yii::$app->session->setFlash(
                    'success',
                    'Almost there! We have sent a confirmation link to ' . $user->email
                    . '. Follow it to activate your account.',
                );

                return $this->redirect(['login']);
            }
        }

        $model->password = '';

        return $this->render('signup', ['model' => $model]);
    }

    /**
     * Activates an account from the e-mailed verification token, then logs in.
     */
    public function actionVerifyEmail(string $token): Response
    {
        $user = User::findByVerificationToken($token);

        if ($user === null) {
            Yii::$app->session->setFlash(
                'error',
                'This verification link is invalid or has expired. Please sign up again.',
            );

            return $this->redirect(['signup']);
        }

        if ($user->activate()) {
            Yii::$app->user->login($user);
            Yii::$app->session->setFlash('success', 'Your account is now active. Welcome aboard!');

            return $this->goHome();
        }

        Yii::$app->session->setFlash('error', 'We could not activate your account. Please try again.');

        return $this->redirect(['login']);
    }
}
