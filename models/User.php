<?php

declare(strict_types=1);

namespace app\models;

use Yii;
use yii\base\NotSupportedException;
use yii\behaviors\TimestampBehavior;
use yii\db\ActiveRecord;
use yii\web\IdentityInterface;

/**
 * @property int $id
 * @property string $username
 * @property string $email
 * @property string $password_hash
 * @property string $auth_key
 * @property string|null $password_reset_token
 * @property string|null $verification_token
 * @property int $role
 * @property int $status
 * @property string $created_at
 * @property string $updated_at
 */
class User extends ActiveRecord implements IdentityInterface
{
    public const int STATUS_DELETED  = 0;
    public const int STATUS_INACTIVE = 9;
    public const int STATUS_ACTIVE   = 10;

    public const int ROLE_ADMIN = 10;
    public const int ROLE_USER  = 1;

    /** Lifetime of an e-mail verification token, in seconds (24h). */
    public const int VERIFICATION_TOKEN_EXPIRE = 86400;

    public static function tableName(): string
    {
        return '{{%user}}';
    }

    public function behaviors(): array
    {
        return [
            // created_at/updated_at are MySQL DATETIME columns, so we store formatted
            // datetimes (not the behavior's default UNIX int, which strict mode rejects).
            'timestamp' => [
                'class' => TimestampBehavior::class,
                'value' => static fn (): string => date('Y-m-d H:i:s'),
            ],
        ];
    }

    public function rules(): array
    {
        return [
            [['username', 'email'], 'required'],
            [['username', 'email'], 'unique'],
            ['email', 'email'],
            ['status', 'default', 'value' => self::STATUS_ACTIVE],
            ['status', 'in', 'range' => [self::STATUS_ACTIVE, self::STATUS_INACTIVE, self::STATUS_DELETED]],
            ['role', 'default', 'value' => self::ROLE_USER],
            ['role', 'in', 'range' => [self::ROLE_ADMIN, self::ROLE_USER]],
        ];
    }

    public function isAdmin(): bool
    {
        return (int)$this->role === self::ROLE_ADMIN;
    }

    // --- IdentityInterface ---

    public static function findIdentity($id): ?self
    {
        return static::findOne(['id' => $id, 'status' => self::STATUS_ACTIVE]);
    }

    public static function findIdentityByAccessToken($token, $type = null): never
    {
        throw new NotSupportedException('"findIdentityByAccessToken" is not implemented.');
    }

    public function getId(): int|string|null
    {
        return $this->getPrimaryKey();
    }

    public function getAuthKey(): ?string
    {
        return $this->auth_key;
    }

    public function validateAuthKey($authKey): bool
    {
        return $this->getAuthKey() === $authKey;
    }

    // --- auth helpers ---

    public static function findByUsername(string $username): ?self
    {
        return static::findOne(['username' => $username, 'status' => self::STATUS_ACTIVE]);
    }

    /**
     * Finds a not-yet-activated user by their (non-expired) verification token.
     */
    public static function findByVerificationToken(string $token): ?self
    {
        if ($token === '' || !self::isVerificationTokenValid($token)) {
            return null;
        }

        return static::findOne(['verification_token' => $token, 'status' => self::STATUS_INACTIVE]);
    }

    /**
     * Whether the timestamp embedded in a verification token is still within its
     * validity window.
     */
    public static function isVerificationTokenValid(string $token): bool
    {
        $parts = explode('_', $token);
        $timestamp = (int)end($parts);

        return $timestamp !== 0 && $timestamp + self::VERIFICATION_TOKEN_EXPIRE >= time();
    }

    public function validatePassword(string $password): bool
    {
        return Yii::$app->security->validatePassword($password, $this->password_hash);
    }

    public function setPassword(string $password): void
    {
        $this->password_hash = Yii::$app->security->generatePasswordHash($password);
    }

    public function generateAuthKey(): void
    {
        $this->auth_key = Yii::$app->security->generateRandomString();
    }

    public function generateVerificationToken(): void
    {
        $this->verification_token = Yii::$app->security->generateRandomString() . '_' . time();
    }

    /**
     * Activates a freshly verified account and clears its one-time token.
     */
    public function activate(): bool
    {
        $this->status = self::STATUS_ACTIVE;
        $this->verification_token = null;

        return $this->save(false);
    }
}
