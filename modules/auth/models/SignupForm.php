<?php

declare(strict_types=1);

namespace app\modules\auth\models;

use app\models\User;
use Yii;
use yii\base\Model;
use yii\mail\MailerInterface;

/**
 * Public self-service sign-up.
 *
 * New accounts are created with the regular {@see User::ROLE_USER} role and an
 * {@see User::STATUS_INACTIVE} status; they become active only after the e-mail
 * verification link is followed. Admin access is never granted here — it is
 * assigned out of band (see the `user/create` console command).
 */
final class SignupForm extends Model
{
    public string $username = '';
    public string $email = '';
    public string $password = '';

    public function rules(): array
    {
        return [
            [['username', 'email', 'password'], 'trim'],
            [['username', 'email', 'password'], 'required'],

            ['username', 'string', 'min' => 2, 'max' => 255],
            ['username', 'match', 'pattern' => '/^[A-Za-z0-9_.-]+$/',
                'message' => 'Username may only contain letters, numbers and . _ -'],
            ['username', 'validateNotReserved'],
            ['username', 'unique', 'targetClass' => User::class,
                'message' => 'This username is already taken.'],

            ['email', 'email'],
            ['email', 'string', 'max' => 255],
            ['email', 'unique', 'targetClass' => User::class,
                'message' => 'This e-mail address is already registered.'],

            ['password', 'string', 'min' => 8],
        ];
    }

    public function attributeLabels(): array
    {
        return [
            'username' => 'Username',
            'email' => 'E-mail address',
            'password' => 'Password',
        ];
    }

    /**
     * Rejects usernames that impersonate an administrator. The value is first
     * normalised (lower-cased, separators removed, common leet substitutions
     * folded back to letters) so look-alikes such as "Adm1n", "4dmin" or
     * "a.d.m.i.n" cannot slip through.
     *
     * @param string $attribute the attribute currently being validated
     */
    public function validateNotReserved(string $attribute): void
    {
        if ($this->hasErrors($attribute)) {
            return;
        }

        $normalized = strtr(
            strtolower(preg_replace('/[._\-\s]+/', '', (string)$this->$attribute) ?? ''),
            ['4' => 'a', '@' => 'a', '0' => 'o', '1' => 'i', '3' => 'e', '5' => 's', '$' => 's', '7' => 't'],
        );

        if (str_contains($normalized, 'admin')) {
            $this->addError($attribute, 'This username is not allowed. Please choose a different one.');
        }
    }

    /**
     * Creates an inactive user awaiting e-mail verification.
     *
     * @return User|null the new (unverified) user, or null on validation failure
     */
    public function signup(): ?User
    {
        if (!$this->validate()) {
            return null;
        }

        $user = new User();
        $user->username = $this->username;
        $user->email = $this->email;
        $user->role = User::ROLE_USER;
        $user->status = User::STATUS_INACTIVE;
        $user->setPassword($this->password);
        $user->generateAuthKey();
        $user->generateVerificationToken();

        return $user->save() ? $user : null;
    }

    /**
     * Sends the account-activation link to the freshly registered user.
     */
    public function sendVerificationEmail(User $user, MailerInterface $mailer): bool
    {
        $verifyUrl = Yii::$app->urlManager->createAbsoluteUrl([
            '/auth/default/verify-email',
            'token' => $user->verification_token,
        ]);

        return $mailer
            ->compose(
                [
                    'html' => '@app/modules/auth/mail/verifyEmail-html',
                    'text' => '@app/modules/auth/mail/verifyEmail-text',
                ],
                ['user' => $user, 'verifyUrl' => $verifyUrl],
            )
            ->setFrom([Yii::$app->params['senderEmail'] => Yii::$app->params['senderName']])
            ->setTo($user->email)
            ->setSubject('Confirm your account at ' . Yii::$app->name)
            ->send();
    }
}
