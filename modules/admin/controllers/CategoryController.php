<?php

declare(strict_types=1);

namespace app\modules\admin\controllers;

use app\models\Category;
use app\modules\admin\models\CategoryImageForm;
use Yii;
use yii\data\ActiveDataProvider;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\web\Response;
use yii\web\UploadedFile;

final class CategoryController extends Controller
{
    public function actionIndex(): string
    {
        return $this->render('index', [
            'dataProvider' => new ActiveDataProvider([
                'query' => Category::find()->orderBy(['level' => SORT_ASC, 'name' => SORT_ASC]),
                'pagination' => ['pageSize' => 50],
            ]),
        ]);
    }

    public function actionUpdate(int $id): Response|string
    {
        $category = Category::findOne($id) ?? throw new NotFoundHttpException('Category not found.');
        $form = new CategoryImageForm();

        if (Yii::$app->request->isPost) {
            $form->load(Yii::$app->request->post());
            $form->file = UploadedFile::getInstance($form, 'file');
            if ($form->apply($category)) {
                Yii::$app->session->setFlash('success', 'Category image updated.');

                return $this->redirect(['index']);
            }
        }

        return $this->render('update', ['category' => $category, 'form' => $form]);
    }
}
