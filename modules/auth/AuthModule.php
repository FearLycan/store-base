<?php

declare(strict_types=1);

namespace app\modules\auth;

use yii\base\Module;

/**
 * Authentication module: login, logout, self-service sign-up with e-mail
 * verification. Kept separate from the storefront / admin so the auth surface
 * has its own controllers, forms, layout and assets.
 */
final class AuthModule extends Module
{
    /** {@inheritdoc} */
    public $controllerNamespace = 'app\modules\auth\controllers';

    /** {@inheritdoc} */
    public $defaultRoute = 'default/login';

    /** Standalone, centred auth shell (modules/auth/views/layouts/auth.php). */
    public $layout = 'auth';
}
