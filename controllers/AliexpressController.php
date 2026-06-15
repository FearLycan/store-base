<?php

declare(strict_types=1);

namespace app\controllers;

use app\components\aliexpress\AliExpressDsClient;
use Throwable;
use Yii;
use yii\web\Controller;
use yii\web\Response;

/**
 * Public OAuth landing for the AliExpress Dropshipping API.
 *
 * AliExpress redirects the seller back here (`/aliexpress/callback`) with the authorization `code`.
 * The redirect target must be reachable without the admin auth gate (AE hits it on a fresh top-level
 * navigation), so it lives outside the admin module. CSRF is covered by the `state` value, which is
 * minted in the admin "authorize" action and verified here against the session.
 */
final class AliexpressController extends Controller
{
    public const STATE_SESSION_KEY = 'ds_oauth_state';

    /** Where the admin returns to after the exchange (success or failure shown via flash). */
    private const RETURN_ROUTE = '/admin/setting/ds';

    public function actionCallback(string $code = '', string $state = '', string $error = ''): Response
    {
        $session = Yii::$app->session;
        $expectedState = (string)$session->get(self::STATE_SESSION_KEY, '');
        $session->remove(self::STATE_SESSION_KEY);

        if ($error !== '') {
            $session->setFlash('danger', 'AliExpress authorization failed: ' . $error);

            return $this->redirect([self::RETURN_ROUTE]);
        }
        if ($expectedState === '' || !hash_equals($expectedState, $state)) {
            $session->setFlash('danger', 'Authorization state mismatch — start the connection again from this page.');

            return $this->redirect([self::RETURN_ROUTE]);
        }
        if ($code === '') {
            $session->setFlash('danger', 'No authorization code received from AliExpress.');

            return $this->redirect([self::RETURN_ROUTE]);
        }

        try {
            (new AliExpressDsClient())->exchangeCode($code);
            $session->setFlash('success', 'Dropshipping API connected — access token stored.');
        } catch (Throwable $e) {
            $session->setFlash('danger', 'Token exchange failed: ' . $e->getMessage());
        }

        return $this->redirect([self::RETURN_ROUTE]);
    }
}
