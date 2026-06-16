<?php
/** @var yii\web\View $this */
use app\components\Seo;
use yii\helpers\Url;

Seo::apply($this, 'Wishlist', 'Products you saved to your wishlist.', Url::to(['/catalog/wishlist'], true), true);
?>
<h1 class="mb-5 text-2xl font-bold">Wishlist</h1>
<div x-data x-cloak>
    <div x-show="$store.shop.favs.length === 0" class="rounded-xl border border-dashed border-gray-200 bg-white p-10 text-center">
        <div class="mx-auto mb-3 flex h-12 w-12 items-center justify-center rounded-full bg-gray-100 text-gray-400">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" class="h-6 w-6"><path d="M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.6l-1-1a5.5 5.5 0 0 0-7.8 7.8l1 1L12 21l7.8-7.6 1-1a5.5 5.5 0 0 0 0-7.8z"/></svg>
        </div>
        <p class="font-medium text-gray-700">Your wishlist is empty</p>
        <p class="mt-1 text-sm text-gray-500">Tap the heart on any product to add it to your wishlist.</p>
    </div>
    <div x-show="$store.shop.favs.length > 0" class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4">
        <template x-for="p in $store.shop.favs" :key="p.slug">
            <?= $this->render('_partials/_client-card') ?>
        </template>
    </div>
</div>
