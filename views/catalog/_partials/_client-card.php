<?php /** @var yii\web\View $this */ ?>
<div class="group relative overflow-hidden rounded-xl border border-gray-200 bg-white transition hover:shadow-md">
    <a :href="p.url" class="block">
        <div class="relative aspect-square overflow-hidden bg-gray-100">
            <img :src="p.image" :alt="p.title" loading="lazy" class="h-full w-full object-cover transition-transform duration-500 group-hover:scale-[1.04]">
        </div>
        <div class="p-3">
            <h3 class="line-clamp-2 min-h-[2.5rem] text-sm text-gray-800" x-text="p.title"></h3>
            <div class="mt-2 text-sm font-bold tabular-nums" x-show="p.price"><span x-text="p.price"></span> <span class="text-xs font-normal text-gray-500" x-text="p.currency"></span></div>
        </div>
    </a>
    <button type="button" class="fav-btn" :class="{ 'is-on': $store.shop.isFav(p.slug) }"
            @click.prevent.stop="$store.shop.toggleFav(p)" :aria-pressed="$store.shop.isFav(p.slug)" aria-label="Toggle wishlist">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.6l-1-1a5.5 5.5 0 0 0-7.8 7.8l1 1L12 21l7.8-7.6 1-1a5.5 5.5 0 0 0 0-7.8z"/></svg>
    </button>
</div>
