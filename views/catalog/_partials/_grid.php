<?php
/** @var yii\web\View $this */
/** @var yii\data\ActiveDataProvider $dataProvider */
/** @var string $empty */
use yii\helpers\Html;
$models = $dataProvider->getModels();
?>
<?php if ($models === []): ?>
    <p class="rounded-xl border border-gray-200 bg-white p-8 text-center text-gray-500"><?= Html::encode($empty) ?></p>
<?php else: ?>
    <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4">
        <?php foreach ($models as $p): ?><?= $this->render('product-card', ['product' => $p]) ?><?php endforeach; ?>
    </div>
    <?= $this->render('pagination', ['pages' => $dataProvider->pagination]) ?>
<?php endif; ?>
