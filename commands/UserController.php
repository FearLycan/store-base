<?php

namespace app\commands;

use app\models\User;
use yii\console\Controller;
use yii\console\ExitCode;
use yii\helpers\Console;

/**
 * User account management from the CLI.
 *
 * Uses backend\models\User so the TIMESTAMP created_at/updated_at columns are
 * written correctly (see the model for details).
 */
class UserController extends Controller
{
    /**
     * Explicit password. When omitted, a strong random one is generated and
     * printed once.
     */
    public ?string $password = null;

    /**
     * Role to assign: "admin" or "user".
     */
    public string $role = 'admin';

    /**
     * Length of the generated random password.
     */
    public int $length = 20;

    public function options($actionID): array
    {
        return array_merge(parent::options($actionID), ['password', 'role', 'length']);
    }

    public function optionAliases(): array
    {
        return array_merge(parent::optionAliases(), [
            'p' => 'password',
            'r' => 'role',
            'l' => 'length',
        ]);
    }

    /**
     * Creates an account (admin by default) with a random password.
     *
     * Examples:
     *   php yii user/create admin
     *   php yii user/create alice alice@example.com
     *   php yii user/create bob bob@example.com --role=user
     *   php yii user/create admin --password=secret123
     *
     * @param string      $username unique username
     * @param string|null $email    contact email (defaults to <username>@admin.local)
     */
    public function actionCreate(string $username, ?string $email = null): int
    {
        $email ??= $username . '@admin.local';

        $roleValue = $this->resolveRole();
        if ($roleValue === null) {
            $this->stderr("Invalid --role: use \"admin\" or \"user\".\n", Console::FG_RED);
            return ExitCode::USAGE;
        }

        if (User::find()->where(['username' => $username])->exists()) {
            $this->stderr("A user named \"$username\" already exists.\n", Console::FG_RED);
            return ExitCode::UNSPECIFIED_ERROR;
        }

        if (User::find()->where(['email' => $email])->exists()) {
            $this->stderr("A user with email \"$email\" already exists.\n", Console::FG_RED);
            return ExitCode::UNSPECIFIED_ERROR;
        }

        $password = $this->password ?? $this->randomPassword(max(8, $this->length));

        $user = new User();
        $user->username = $username;
        $user->email = $email;
        $user->role = $roleValue;
        $user->status = User::STATUS_ACTIVE;
        $user->generateAuthKey();
        $user->setPassword($password);

        if (!$user->save(false)) {
            $this->stderr("Failed to save the user:\n", Console::FG_RED);
            $this->stderr(print_r($user->getErrors(), true));
            return ExitCode::UNSPECIFIED_ERROR;
        }

        $roleLabel = $roleValue === User::ROLE_ADMIN ? 'admin' : 'user';

        $this->stdout("\n");
        $this->stdout("Account created.\n", Console::FG_GREEN, Console::BOLD);
        $this->stdout("  id:       {$user->id}\n");
        $this->stdout("  username: {$user->username}\n");
        $this->stdout("  email:    {$user->email}\n");
        $this->stdout("  role:     {$roleLabel}\n");
        $this->stdout('  password: ');
        $this->stdout("$password\n", Console::FG_YELLOW, Console::BOLD);
        $this->stdout("\nStore the password now — it is not recoverable.\n", Console::FG_GREY);

        return ExitCode::OK;
    }

    private function resolveRole(): ?int
    {
        return match (strtolower($this->role)) {
            'admin' => User::ROLE_ADMIN,
            'user' => User::ROLE_USER,
            default => null,
        };
    }

    /**
     * Cryptographically secure password from an unambiguous character set
     * (no 0/O/1/l/I) so it survives being read off a terminal.
     */
    private function randomPassword(int $length): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789!@#%^*-_';
        $max = strlen($alphabet) - 1;
        $password = '';

        for ($i = 0; $i < $length; $i++) {
            $password .= $alphabet[random_int(0, $max)];
        }

        return $password;
    }
}
