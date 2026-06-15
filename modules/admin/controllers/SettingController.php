<?php

declare(strict_types=1);

namespace app\modules\admin\controllers;

use app\components\aliexpress\AliExpressDsClient;
use app\components\aliexpress\AliExpressStoreScraper;
use app\controllers\AliexpressController;
use app\models\Setting;
use app\models\Store;
use Throwable;
use Yii;
use yii\web\Controller;
use yii\web\Response;

/**
 * Admin screen to paste/refresh the AliExpress session cookie (incl. x5sec) that the
 * store-listing scraper needs, plus a one-click connection test against a real store.
 */
final class SettingController extends Controller
{
    public function actionIndex(): Response|string
    {
        if (Yii::$app->request->isPost) {
            $cookie = trim((string)Yii::$app->request->post('cookie', ''));
            Setting::set(Setting::ALIEXPRESS_COOKIE, $cookie);
            Yii::$app->session->setFlash('success', 'AliExpress session cookie saved.');

            return $this->redirect(['index']);
        }

        return $this->render('index', [
            'cookie'    => (string)Setting::get(Setting::ALIEXPRESS_COOKIE, ''),
            'updatedAt' => Setting::updatedAt(Setting::ALIEXPRESS_COOKIE),
        ]);
    }

    public function actionAppearance(): Response|string
    {
        if (Yii::$app->request->isPost) {
            Setting::set('site.custom_css', (string)Yii::$app->request->post('custom_css', ''));
            Yii::$app->session->setFlash('success', 'Custom CSS saved.');

            return $this->redirect(['appearance']);
        }

        return $this->render('appearance', [
            'customCss' => (string)Setting::get('site.custom_css', ''),
            'updatedAt' => Setting::updatedAt('site.custom_css'),
        ]);
    }

    /** Dropshipping API status + connect screen. */
    public function actionDs(): string
    {
        $client = new AliExpressDsClient();

        return $this->render('ds', [
            'connected'   => $client->isConnected(),
            'expiresAt'   => (int)Setting::get(Setting::DS_TOKEN_EXPIRES_AT, '0'),
            'updatedAt'   => Setting::updatedAt(Setting::DS_ACCESS_TOKEN),
            'callbackUrl' => $this->dsCallbackUrl(),
            'callbackConfigured' => trim((string)(Yii::$app->params['aliexpress.dropshipping.callbackUrl'] ?? '')) !== '',
        ]);
    }

    /**
     * Kick off the OAuth flow: mint a CSRF `state`, then bounce the admin to the AliExpress consent
     * page. AE redirects back to the public {@see \app\controllers\AliexpressController::actionCallback}.
     */
    public function actionDsAuthorize(): Response
    {
        if (trim((string)(Yii::$app->params['aliexpress.dropshipping.callbackUrl'] ?? '')) === '') {
            Yii::$app->session->setFlash('warning', 'Set aliexpress.dropshipping.callbackUrl in params-local.php (and register the same URL in the AliExpress console) before authorizing.');

            return $this->redirect(['ds']);
        }

        $state = Yii::$app->security->generateRandomString(32);
        Yii::$app->session->set(AliexpressController::STATE_SESSION_KEY, $state);

        return $this->redirect((new AliExpressDsClient())->authorizeUrl($this->dsCallbackUrl(), $state));
    }

    /** One-click smoke test: refresh the token and pull one real product through the DS API. */
    public function actionDsTest(): Response
    {
        $product = \app\models\Product::find()->orderBy(['id' => SORT_ASC])->one();
        if ($product === null) {
            Yii::$app->session->setFlash('warning', 'Import a product first, then test.');

            return $this->redirect(['ds']);
        }

        try {
            $detail = (new AliExpressDsClient())->fetch((string)$product->external_id);
            Yii::$app->session->setFlash('success', sprintf(
                'OK — DS API returned %d image(s), %d variant(s), %d spec(s) for "%s".',
                count($detail['images']),
                count($detail['variants']),
                count($detail['attributes']),
                (string)$product->title,
            ));
        } catch (Throwable $e) {
            Yii::$app->session->setFlash('danger', 'DS test failed: ' . $e->getMessage());
        }

        return $this->redirect(['ds']);
    }

    private function dsCallbackUrl(): string
    {
        $configured = trim((string)(Yii::$app->params['aliexpress.dropshipping.callbackUrl'] ?? ''));

        return $configured !== '' ? $configured : Yii::$app->urlManager->createAbsoluteUrl(['/aliexpress/callback']);
    }

    public function actionTest(): Response
    {
        $store = Store::find()->orderBy(['id' => SORT_ASC])->one();
        if ($store === null) {
            Yii::$app->session->setFlash('warning', 'Add a store first, then test.');

            return $this->redirect(['index']);
        }

        try {
            $stubs = (new AliExpressStoreScraper())->fetchProductStubs($store, 1);
            Yii::$app->session->setFlash('success', sprintf(
                'OK — fetched %d product(s) from page 1 of "%s" (sellerId %s). Cookie works.',
                count($stubs),
                $store->name,
                $store->seller_id ?? '?',
            ));
        } catch (Throwable $e) {
            Yii::$app->session->setFlash('danger', 'Test failed: ' . $e->getMessage());
        }

        return $this->redirect(['index']);
    }
}
