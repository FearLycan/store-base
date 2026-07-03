<?php
/** @var yii\web\View $this */
/** @var app\models\Store[] $stores */
/** @var array<int,int> $counts store id => active product count */

use app\components\Seo;
use yii\helpers\Html;
use yii\helpers\Url;

Seo::apply($this, 'Stores', 'Browse all stores in the catalog.', Url::to(['/store/index'], true));
?>
<?= $this->render('//catalog/_partials/breadcrumbs', ['items' => [
    ['name' => 'Home', 'url' => Url::to(['/catalog/index'])],
    ['name' => 'Stores', 'url' => null],
]]) ?>
<h1 class="mb-1 text-2xl font-bold">Stores</h1>
<p class="mb-6 text-sm text-gray-500"><?= number_format(count($stores)) ?> store<?= count($stores) === 1 ? '' : 's' ?></p>

<?php if ($stores === []): ?>
    <p class="rounded-xl border border-gray-200 bg-white p-8 text-center text-gray-500">No stores yet.</p>
<?php else: ?>
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
        <?php foreach ($stores as $s): ?>
            <?php $count = $counts[$s->id] ?? 0; ?>
            <a href="<?= Url::to(['/store/view', 'slug' => $s->slug]) ?>"
               class="group flex items-center gap-4 rounded-2xl border border-gray-200 bg-white p-4 transition-colors hover:border-[color:var(--accent)]">
                <?= $this->render('_logo', ['store' => $s, 'size' => 'md']) ?>
                <span class="min-w-0 flex-1">
                    <span class="block truncate font-semibold text-gray-900 group-hover:text-[color:var(--accent)]"><?= Html::encode($s->name) ?></span>
                    <span class="mt-0.5 block text-sm text-gray-500"><?= number_format($count) ?> product<?= $count === 1 ? '' : 's' ?></span>
                </span>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-5 w-5 flex-none text-gray-300 group-hover:text-[color:var(--accent)]" aria-hidden="true">
                    <polyline points="9 18 15 12 9 6"/>
                </svg>
            </a>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
