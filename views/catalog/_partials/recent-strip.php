<?php
/** @var yii\web\View $this */
/** @var string $excludeSlug Optional slug to hide (the current product on a PDP). */
use yii\helpers\Json;
$excludeSlug = $excludeSlug ?? '';
?>
<section x-data="{ exclude: <?= Json::encode($excludeSlug) ?>, get items() { return $store.shop.recent.filter((r) => !this.exclude || r.slug !== this.exclude); } }"
         x-show="items.length > 0" x-cloak class="mb-12 mt-12">
    <h2 class="mb-4 text-xl font-bold tracking-tight" style="text-wrap: balance;">Recently viewed</h2>
    <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-6">
        <template x-for="p in items" :key="p.slug">
            <?= $this->render('_client-card') ?>
        </template>
    </div>
</section>
