<?php

/** @var yii\web\View $this */

use yii\helpers\Html;
use yii\helpers\Url;

$suggestUrl = Url::to(['/catalog/suggest']);
$searchUrl  = Url::to(['/catalog/search']);
$allUrl     = Url::to(['/catalog/all']);
?>
<div x-data="searchModal('<?= Html::encode($suggestUrl) ?>', '<?= Html::encode($searchUrl) ?>')"
     x-show="open" x-cloak
     @search-open.window="show($event.detail)"
     @keydown.window="onGlobalKey($event)"
     class="fixed inset-0 z-50" role="dialog" aria-modal="true" aria-label="Search products">

    <!-- Backdrop -->
    <div x-show="open"
         x-transition:enter="search-fade-enter" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
         x-transition:leave="search-fade-leave" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
         class="absolute inset-0 bg-gray-950/40 backdrop-blur-[2px]" @click="close()"></div>

    <!-- Panel -->
    <div x-show="open"
         x-transition:enter="search-panel-enter" x-transition:enter-start="opacity-0 translate-y-2 scale-[0.98]" x-transition:enter-end="opacity-100 translate-y-0 scale-100"
         x-transition:leave="search-panel-leave" x-transition:leave-start="opacity-100 translate-y-0" x-transition:leave-end="opacity-0 translate-y-1"
         class="search-panel relative mx-auto mt-[8vh] flex w-[min(40rem,calc(100vw-2rem))] flex-col overflow-hidden rounded-2xl bg-white sm:mt-[12vh]">

        <!-- Input row -->
        <div class="relative flex items-center gap-3 px-5 py-4">
            <svg class="h-5 w-5 shrink-0 text-gray-400" viewBox="0 0 20 20" fill="none" aria-hidden="true">
                <circle cx="9" cy="9" r="6" stroke="currentColor" stroke-width="1.7"/>
                <path d="m13.5 13.5 3.5 3.5" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/>
            </svg>
            <input x-ref="input" x-model="q" @input="onInput()"
                   @keydown.down.prevent="move(1)" @keydown.up.prevent="move(-1)"
                   @keydown.enter.prevent="go()" @keydown.escape.prevent="close()"
                   type="text" placeholder="Search products…" autocomplete="off" spellcheck="false"
                   role="combobox" aria-expanded="true" aria-autocomplete="list" aria-controls="search-modal-list"
                   class="w-full border-0 bg-transparent text-base text-gray-900 placeholder-gray-400 focus:outline-none focus:ring-0">
            <button type="button" @click="close()" class="search-hit-area relative -mr-1 hidden sm:block" aria-label="Close search">
                <kbd class="kbd">esc</kbd>
            </button>
            <!-- Loading shimmer -->
            <div class="absolute inset-x-0 bottom-0 h-px overflow-hidden" aria-hidden="true">
                <div x-show="loading" class="search-loading-bar h-full w-1/3"></div>
            </div>
        </div>

        <div class="border-t border-gray-100"></div>

        <!-- Results -->
        <div x-ref="list" id="search-modal-list" role="listbox"
             class="search-scroll max-h-[min(26rem,62vh)] overflow-y-auto overscroll-contain p-2">

            <!-- Recent searches (empty query only) -->
            <template x-if="!q.trim() && recent.length">
                <div class="px-2 pb-1 pt-2">
                    <div class="flex items-center justify-between">
                        <p class="search-label">Recent</p>
                        <button type="button" @click="clearRecent()"
                                class="text-xs text-gray-400 transition-colors hover:text-gray-600">Clear</button>
                    </div>
                    <div class="mt-2 flex flex-wrap gap-1.5">
                        <template x-for="r in recent" :key="r">
                            <button type="button" @click="q = r; onInput()"
                                    class="search-chip" x-text="r"></button>
                        </template>
                    </div>
                </div>
            </template>

            <!-- Category matches -->
            <template x-if="results.categories.length">
                <div class="px-2 pt-2">
                    <p class="search-label" x-text="mode === 'popular' ? 'Browse categories' : 'Categories'"></p>
                    <div class="mt-1.5 flex flex-wrap gap-1.5 pb-1">
                        <template x-for="(c, ci) in results.categories" :key="c.url">
                            <a :href="c.url" :data-idx="ci" role="option" :aria-selected="sel === ci"
                               @mouseenter="sel = ci" @click="remember()"
                               class="search-chip search-chip--cat" :class="sel === ci && 'is-active'">
                                <span class="search-hit" x-html="hl(c.name)"></span>
                                <svg class="h-3 w-3 opacity-50" viewBox="0 0 16 16" fill="none" aria-hidden="true">
                                    <path d="M6 3.5 10.5 8 6 12.5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                            </a>
                        </template>
                    </div>
                </div>
            </template>

            <!-- Product results -->
            <template x-if="results.products.length">
                <div class="pt-2">
                    <p class="search-label px-4" x-text="mode === 'popular' ? 'Popular right now' : 'Products'"></p>
                    <div class="mt-1">
                        <template x-for="(p, pi) in results.products" :key="p.url + q">
                            <a :href="p.url" :data-idx="catCount + pi" role="option" :aria-selected="sel === catCount + pi"
                               @mouseenter="sel = catCount + pi" @click="remember()"
                               :style="`--i:${pi}`"
                               class="search-row group flex items-center gap-3 rounded-xl px-2 py-2"
                               :class="sel === catCount + pi && 'is-active'">
                                <img :src="p.image" :alt="p.title" loading="lazy"
                                     class="search-thumb h-12 w-12 shrink-0 rounded-lg bg-gray-100 object-cover">
                                <div class="min-w-0 flex-1">
                                    <p class="search-hit truncate text-sm text-gray-800" x-html="hl(p.title)"></p>
                                    <p class="mt-0.5 flex items-baseline gap-2 text-xs text-gray-500">
                                        <template x-if="p.price">
                                            <span class="flex items-baseline gap-1.5">
                                                <span class="text-sm font-bold tabular-nums text-gray-900" x-text="`${p.price} ${p.currency}`"></span>
                                                <template x-if="p.originalPrice">
                                                    <span class="tabular-nums text-gray-400 line-through" x-text="p.originalPrice"></span>
                                                </template>
                                                <template x-if="p.discount">
                                                    <span class="search-discount" x-text="`−${p.discount}%`"></span>
                                                </template>
                                            </span>
                                        </template>
                                        <template x-if="!p.price">
                                            <span class="text-sm font-semibold text-[color:var(--accent)]">Check price</span>
                                        </template>
                                        <template x-if="p.rating">
                                            <span class="whitespace-nowrap text-amber-500">★ <span class="tabular-nums text-gray-500" x-text="p.rating"></span></span>
                                        </template>
                                        <template x-if="p.orders">
                                            <span class="whitespace-nowrap tabular-nums" x-text="`${p.orders} orders`"></span>
                                        </template>
                                    </p>
                                </div>
                                <svg class="h-4 w-4 shrink-0 text-gray-300 opacity-0 transition-opacity duration-150 group-[.is-active]:opacity-100" viewBox="0 0 16 16" fill="none" aria-hidden="true">
                                    <path d="M6 3.5 10.5 8 6 12.5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                            </a>
                        </template>
                    </div>
                </div>
            </template>

            <!-- View all results -->
            <template x-if="mode === 'results' && results.products.length">
                <a :href="searchHref()" :data-idx="catCount + results.products.length" role="option"
                   :aria-selected="sel === catCount + results.products.length"
                   @mouseenter="sel = catCount + results.products.length" @click="remember()"
                   class="search-row mt-1 flex items-center justify-between rounded-xl px-4 py-3 text-sm font-semibold text-[color:var(--accent)]"
                   :class="sel === catCount + results.products.length && 'is-active'"
                   :style="`--i:${results.products.length}`">
                    <span x-text="`View all ${results.total} results for “${q.trim()}”`"></span>
                    <span aria-hidden="true">→</span>
                </a>
            </template>

            <!-- Empty state -->
            <template x-if="mode === 'results' && !loading && !results.products.length && !results.categories.length">
                <div class="px-4 py-12 text-center">
                    <div class="search-empty-icon mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-gray-100 text-gray-400">
                        <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                            <circle cx="11" cy="11" r="7" stroke="currentColor" stroke-width="1.7"/>
                            <path d="m16.5 16.5 4 4M8.5 11h5" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/>
                        </svg>
                    </div>
                    <p class="mt-4 text-sm font-medium text-gray-900">No results for “<span x-text="q.trim()"></span>”</p>
                    <p class="mt-1 text-sm text-gray-500">Try a different spelling or fewer words.</p>
                    <a href="<?= $allUrl ?>" class="mt-4 inline-block text-sm font-semibold text-[color:var(--accent)] hover:underline">Browse all products</a>
                </div>
            </template>
        </div>

        <!-- Footer hints -->
        <div class="hidden items-center gap-4 border-t border-gray-100 bg-gray-50/70 px-4 py-2 text-[11px] text-gray-400 sm:flex">
            <span class="flex items-center gap-1"><kbd class="kbd">↑</kbd><kbd class="kbd">↓</kbd> navigate</span>
            <span class="flex items-center gap-1"><kbd class="kbd">↵</kbd> open</span>
            <span class="flex items-center gap-1"><kbd class="kbd">esc</kbd> close</span>
        </div>
    </div>
</div>

<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('searchModal', (suggestUrl, searchUrl) => ({
        open: false,
        q: '',
        loading: false,
        mode: 'popular',
        results: { categories: [], products: [], total: 0 },
        recent: [],
        sel: 0,
        _timer: null,
        _abort: null,
        _lastFetched: null,

        get catCount() { return this.results.categories.length; },
        get itemCount() {
            return this.catCount + this.results.products.length
                + (this.mode === 'results' && this.results.products.length ? 1 : 0);
        },

        show(prefill) {
            this.open = true;
            if (typeof prefill === 'string' && prefill.trim()) { this.q = prefill; }
            try { this.recent = JSON.parse(localStorage.getItem('shop.recent-searches') || '[]'); } catch (e) { this.recent = []; }
            document.body.style.overflow = 'hidden';
            this.$nextTick(() => { this.$refs.input.focus(); this.$refs.input.select(); });
            if (this._lastFetched !== this.q.trim()) { this.fetch(0); }
        },

        close() {
            this.open = false;
            document.body.style.overflow = '';
        },

        onGlobalKey(e) {
            if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'k') {
                e.preventDefault();
                this.open ? this.close() : this.show();
                return;
            }
            if (e.key === '/' && !this.open && !/^(input|textarea|select)$/i.test(e.target.tagName) && !e.target.isContentEditable) {
                e.preventDefault();
                this.show();
            }
        },

        onInput() { this.fetch(180); },

        fetch(delay) {
            clearTimeout(this._timer);
            this.loading = true;
            this._timer = setTimeout(async () => {
                this._abort?.abort();
                this._abort = new AbortController();
                const q = this.q.trim();
                try {
                    const res = await window.fetch(`${suggestUrl}?q=${encodeURIComponent(q)}`, {
                        signal: this._abort.signal,
                        headers: { 'Accept': 'application/json' },
                    });
                    if (!res.ok) { throw new Error(res.status); }
                    const data = await res.json();
                    this.results = { categories: data.categories, products: data.products, total: data.total };
                    this.mode = data.mode;
                    this._lastFetched = q;
                    this.sel = this.catCount; // first product (or "view all" / nothing)
                    if (!this.results.products.length) { this.sel = 0; }
                    this.loading = false;
                } catch (err) {
                    if (err.name !== 'AbortError') { this.loading = false; }
                }
            }, delay);
        },

        move(dir) {
            if (!this.itemCount) { return; }
            this.sel = (this.sel + dir + this.itemCount) % this.itemCount;
            this.$nextTick(() => {
                this.$refs.list.querySelector(`[data-idx="${this.sel}"]`)?.scrollIntoView({ block: 'nearest' });
            });
        },

        go() {
            const el = this.$refs.list.querySelector(`[data-idx="${this.sel}"]`);
            const href = el?.getAttribute('href') || (this.q.trim() ? this.searchHref() : null);
            if (!href) { return; }
            this.remember();
            window.location.assign(href);
        },

        searchHref() { return `${searchUrl}?q=${encodeURIComponent(this.q.trim())}`; },

        remember() {
            const q = this.q.trim();
            if (!q) { return; }
            const next = [q, ...this.recent.filter(r => r.toLowerCase() !== q.toLowerCase())].slice(0, 6);
            this.recent = next;
            try { localStorage.setItem('shop.recent-searches', JSON.stringify(next)); } catch (e) {}
        },

        clearRecent() {
            this.recent = [];
            try { localStorage.removeItem('shop.recent-searches'); } catch (e) {}
        },

        // Escape, then wrap query-term hits in <mark> in a single pass.
        hl(text) {
            const esc = s => s.replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
            const safe = esc(String(text));
            const terms = this.q.trim().split(/\s+/).filter(t => t.length > 1).map(esc)
                .map(t => t.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'));
            if (!terms.length) { return safe; }
            return safe.replace(new RegExp(`(${terms.join('|')})`, 'ig'), '<mark>$1</mark>');
        },
    }));
});
</script>
