<?php

declare(strict_types=1);

namespace app\modules\admin\controllers;

use app\components\aliexpress\AliExpressStoreScraper;
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
