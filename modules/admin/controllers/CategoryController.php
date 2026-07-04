<?php

declare(strict_types=1);

namespace app\modules\admin\controllers;

use app\enums\CategoryStatusEnum;
use app\models\Category;
use app\modules\admin\models\CategoryContentForm;
use app\modules\admin\models\CategoryImageForm;
use Yii;
use yii\data\ActiveDataProvider;
use yii\filters\VerbFilter;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\web\Response;
use yii\web\UploadedFile;

final class CategoryController extends Controller
{
    public function behaviors(): array
    {
        return [
            'verbs' => [
                'class' => VerbFilter::class,
                'actions' => ['toggle-status' => ['post']],
            ],
        ];
    }

    public function actionIndex(?string $q = null, ?string $status = null): string
    {
        $query = Category::find();

        $q = trim((string)$q);
        if ($q !== '') {
            $query->andWhere(['like', 'name', $q]);
        }

        $status = (string)$status;
        if ($status !== '' && CategoryStatusEnum::tryFrom($status) !== null) {
            $query->andWhere(['status' => $status]);
        }

        return $this->render('index', [
            'dataProvider' => new ActiveDataProvider([
                'query' => $query,
                'pagination' => ['pageSize' => 50],
                'sort' => ['defaultOrder' => ['level' => SORT_ASC, 'name' => SORT_ASC]],
            ]),
            'q' => $q,
            'status' => $status,
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

        $contentForm = new CategoryContentForm();
        $contentForm->loadFrom($category);

        return $this->render('update', ['category' => $category, 'form' => $form, 'contentForm' => $contentForm]);
    }

    /**
     * Flip a category between active and inactive from the list, over AJAX.
     * Inactive hides the category, its subtree and their products from the
     * storefront (see Category::hiddenIds + CatalogQuery::active).
     *
     * @return array{status: string, label: string, badgeClass: string, active: bool}
     */
    public function actionToggleStatus(int $id): array
    {
        Yii::$app->response->format = Response::FORMAT_JSON;

        $category = Category::findOne($id) ?? throw new NotFoundHttpException('Category not found.');
        $next = $category->isActive() ? CategoryStatusEnum::INACTIVE : CategoryStatusEnum::ACTIVE;

        $category->status = $next->value;
        $category->save(false, ['status']);

        return [
            'status'     => $next->value,
            'label'      => $next->label(),
            'badgeClass' => $next->badgeClass(),
            'active'     => $next === CategoryStatusEnum::ACTIVE,
        ];
    }

    public function actionContent(int $id): Response|string
    {
        $category = Category::findOne($id) ?? throw new NotFoundHttpException('Category not found.');
        $contentForm = new CategoryContentForm();

        if ($contentForm->load(Yii::$app->request->post()) && $contentForm->apply($category)) {
            Yii::$app->session->setFlash('success', 'Category content updated.');

            return $this->redirect(['update', 'id' => $id]);
        }

        return $this->render('update', ['category' => $category, 'form' => new CategoryImageForm(), 'contentForm' => $contentForm]);
    }
}
