<?php

declare(strict_types=1);

namespace app\modules\admin;

use Yii;
use yii\base\Action;
use yii\base\Module;
use yii\web\ForbiddenHttpException;

/**
 * Admin module.
 *
 * Access is gated for the whole module: every controller/action below `/admin`
 * requires an authenticated user holding the {@see \app\models\User::ROLE_ADMIN}
 * role. Guests are sent to the login screen; signed-in non-admins get a 403.
 */
final class AdminModule extends Module
{
    /** {@inheritdoc} */
    public $controllerNamespace = 'app\modules\admin\controllers';

    /** {@inheritdoc} */
    public $defaultRoute = 'dashboard/index';

    /** Admin shell (modules/admin/views/layouts/main.php). */
    public $layout = 'main';

    /**
     * @param Action $action
     * @throws ForbiddenHttpException when a signed-in user is not an admin
     */
    public function beforeAction($action): bool
    {
        if (!parent::beforeAction($action)) {
            return false;
        }

        $user = Yii::$app->user;

        if ($user->isGuest) {
            $user->loginRequired();

            return false;
        }

        if (!$user->identity->isAdmin()) {
            throw new ForbiddenHttpException('You do not have permission to access the admin area.');
        }

        return true;
    }
}
