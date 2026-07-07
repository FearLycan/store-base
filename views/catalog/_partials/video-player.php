<?php
/** @var app\models\Product[] $videos */
/** @var string $layout 'rail' (horizontal strip) | 'grid' (paginated listing). Default 'rail'. */
use yii\helpers\Html;
use yii\helpers\Json;
use yii\helpers\Url;

if ($videos === []) {
    return;
}
$layout = ($layout ?? 'rail') === 'grid' ? 'grid' : 'rail';

// Compact payload the modal cycles through (one MP4 per product).
$items = array_map(static function (app\models\Product $p): array {
    return [
        'url'    => (string)$p->video_url,
        'poster' => $p->main_image ?: '/img/placeholder.png',
        'title'  => $p->displayName,
        'href'   => Url::to(['/product/view', 'slug' => $p->slug]),
        'price'  => $p->price !== null
            ? number_format($p->price / 100, 2) . ' ' . ($p->currency_code ?: 'USD')
            : null,
    ];
}, $videos);

$play = '<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true" class="h-5 w-5 translate-x-px"><path d="M8 5.5v13l11-6.5z"/></svg>';
?>
<div x-data="videoWall(<?= Html::encode(Json::encode($items)) ?>)" @keydown.window="onKey($event)">
    <div class="<?= $layout === 'grid' ? 'video-grid' : 'video-rail' ?>">
        <?php foreach ($items as $i => $it): ?>
        <button type="button" class="video-card group" @click="open(<?= $i ?>)" aria-label="<?= Html::encode('Play video: ' . $it['title']) ?>">
            <img src="<?= Html::encode($it['poster']) ?>" alt="" loading="lazy" decoding="async" class="video-card-img">
            <span class="video-card-scrim" aria-hidden="true"></span>
            <span class="video-card-play" aria-hidden="true"><?= $play ?></span>
            <span class="video-card-body">
                <span class="video-card-title"><?= Html::encode($it['title']) ?></span>
                <?php if ($it['price'] !== null): ?>
                <span class="video-card-price"><?= Html::encode($it['price']) ?></span>
                <?php endif; ?>
            </span>
        </button>
        <?php endforeach; ?>
    </div>

    <!-- Player modal (matches the dark, focused player chrome) -->
    <div x-show="active" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4 sm:p-6"
         role="dialog" aria-modal="true" aria-label="Product video">
        <div x-show="active"
             x-transition:enter="vw-fade-enter" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
             x-transition:leave="vw-fade-leave" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
             class="absolute inset-0 bg-gray-950/80 backdrop-blur-sm" @click="close()"></div>

        <div x-show="active"
             x-transition:enter="vw-panel-enter" x-transition:enter-start="opacity-0 translate-y-3 scale-[0.98]" x-transition:enter-end="opacity-100 translate-y-0 scale-100"
             x-transition:leave="vw-panel-leave" x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-[0.98]"
             class="vw-panel relative w-[min(60rem,100%)]">

            <div class="flex items-center justify-between gap-4 px-4 py-3 sm:px-5">
                <h3 class="min-w-0 truncate text-sm font-semibold text-white sm:text-base" x-text="current.title"></h3>
                <button type="button" @click="close()" class="vw-close" aria-label="Close video">
                    <svg viewBox="0 0 16 16" fill="none" class="h-4 w-4"><path d="m4 4 8 8m0-8-8 8" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/></svg>
                </button>
            </div>

            <div class="vw-stage">
                <video x-ref="video" :src="current.url" :poster="current.poster"
                       controls playsinline preload="metadata" class="h-full w-full bg-black object-contain"></video>

                <template x-if="items.length > 1">
                    <button type="button" @click="go(-1)" class="vw-nav left-2.5 sm:left-3" aria-label="Previous video">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-5 w-5"><polyline points="15 18 9 12 15 6"/></svg>
                    </button>
                </template>
                <template x-if="items.length > 1">
                    <button type="button" @click="go(1)" class="vw-nav right-2.5 sm:right-3" aria-label="Next video">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-5 w-5"><polyline points="9 18 15 12 9 6"/></svg>
                    </button>
                </template>
            </div>

            <div class="flex items-center justify-between gap-4 px-4 py-3 sm:px-5">
                <span class="text-sm font-bold tabular-nums text-white" x-text="current.price"></span>
                <a :href="current.href" class="vw-cta">
                    View product
                    <svg viewBox="0 0 16 16" fill="none" class="h-3.5 w-3.5"><path d="M6 3.5 10.5 8 6 12.5" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg>
                </a>
            </div>
        </div>
    </div>
</div>

<?php
// Register the Alpine component once per request even if the partial is rendered
// more than once (each instance still gets its own isolated state via x-data).
if (!isset($this->params['videoWallScript'])):
    $this->params['videoWallScript'] = true;
?>
<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('videoWall', (items) => ({
        items: items,
        active: false,
        i: 0,

        get current() { return this.items[this.i] || {}; },

        open(i) {
            this.i = i;
            this.active = true;
            document.body.style.overflow = 'hidden';
            this.$nextTick(() => this.load());
        },

        close() {
            this.active = false;
            document.body.style.overflow = '';
            const v = this.$refs.video;
            if (v) { v.pause(); }
        },

        go(dir) {
            if (this.items.length < 2) { return; }
            this.i = (this.i + dir + this.items.length) % this.items.length;
            this.$nextTick(() => this.load());
        },

        // Re-point the <video> at the current clip and try to autoplay. The
        // :src binding updates the attribute; load() forces the swap, and a
        // rejected play() (autoplay policy) is harmless — controls stay put.
        load() {
            const v = this.$refs.video;
            if (!v) { return; }
            v.load();
            const p = v.play();
            if (p && p.catch) { p.catch(() => {}); }
        },

        onKey(e) {
            if (!this.active) { return; }
            if (e.key === 'Escape') { this.close(); }
            else if (e.key === 'ArrowRight') { this.go(1); }
            else if (e.key === 'ArrowLeft') { this.go(-1); }
        },
    }));
});
</script>
<?php endif; ?>
