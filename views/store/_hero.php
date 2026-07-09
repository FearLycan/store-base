<?php
/**
 * Store hero slider: admin-managed banners, cross-fading. One banner renders
 * as a static image (no controls, no JS). Autoplay is disabled under
 * prefers-reduced-motion — arrows/dots still work.
 *
 * @var yii\web\View $this
 * @var app\models\StoreBanner[] $banners
 * @var app\models\Store $store
 */

use yii\helpers\Html;
use yii\web\View;

$multi = count($banners) > 1;
?>
<section class="store-hero mb-10" data-hero role="region" aria-roledescription="carousel" aria-label="<?= Html::encode($store->name) ?> highlights">
    <div class="store-hero-track">
        <?php foreach ($banners as $i => $banner): ?>
            <?php
            $tag  = $banner->link_url ? 'a' : 'div';
            $attr = $banner->link_url ? ' href="' . Html::encode($banner->link_url) . '"' : '';
            ?>
            <<?= $tag ?><?= $attr ?> class="store-hero-slide<?= $i === 0 ? ' is-on' : '' ?>" data-slide aria-hidden="<?= $i === 0 ? 'false' : 'true' ?>">
                <img src="<?= Html::encode($banner->image_url) ?>"
                     alt="<?= Html::encode($banner->headline ?? $store->name) ?>"
                     class="store-hero-img" decoding="async"
                     <?= $i === 0 ? 'loading="eager" fetchpriority="high"' : 'loading="lazy"' ?>>
                <span class="store-hero-scrim" aria-hidden="true"></span>
                <?php if ($banner->headline !== null || $banner->subheadline !== null || $banner->link_url !== null): ?>
                    <span class="store-hero-copy">
                        <?php if ($banner->headline !== null): ?><span class="store-hero-title"><?= Html::encode($banner->headline) ?></span><?php endif; ?>
                        <?php if ($banner->subheadline !== null): ?><span class="store-hero-sub"><?= Html::encode($banner->subheadline) ?></span><?php endif; ?>
                        <?php if ($banner->link_url !== null): ?><span class="store-hero-cta">Shop now
                            <svg viewBox="0 0 16 16" fill="none" aria-hidden="true" class="h-3.5 w-3.5"><path d="M6 3.5 10.5 8 6 12.5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        </span><?php endif; ?>
                    </span>
                <?php endif; ?>
            </<?= $tag ?>>
        <?php endforeach; ?>
    </div>
    <?php if ($multi): ?>
        <button type="button" class="store-hero-arrow store-hero-prev" data-prev aria-label="Previous banner">
            <svg viewBox="0 0 16 16" fill="none" aria-hidden="true" class="h-4 w-4"><path d="M10 3.5 5.5 8 10 12.5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
        </button>
        <button type="button" class="store-hero-arrow store-hero-next" data-next aria-label="Next banner">
            <svg viewBox="0 0 16 16" fill="none" aria-hidden="true" class="h-4 w-4"><path d="M6 3.5 10.5 8 6 12.5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
        </button>
        <div class="store-hero-dots" data-dots>
            <?php foreach ($banners as $i => $banner): ?>
                <button type="button" class="store-hero-dot<?= $i === 0 ? ' is-on' : '' ?>" data-dot="<?= $i ?>" aria-label="Go to banner <?= $i + 1 ?>"></button>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
<?php if ($multi): ?>
<?php $this->registerJs(<<<'JS'
(function () {
    var hero = document.querySelector('[data-hero]');
    if (!hero) { return; }
    var slides = hero.querySelectorAll('[data-slide]');
    if (slides.length < 2) { return; }
    var dots = hero.querySelectorAll('[data-dot]');
    var SLIDE_MS = 5000;
    var current = 0, timer = 0;
    var reduced = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    function show(next) {
        next = (next + slides.length) % slides.length;
        for (var i = 0; i < slides.length; i++) {
            slides[i].classList.toggle('is-on', i === next);
            slides[i].setAttribute('aria-hidden', i === next ? 'false' : 'true');
        }
        for (var j = 0; j < dots.length; j++) {
            dots[j].classList.toggle('is-on', j === next);
        }
        current = next;
    }

    function play() {
        if (reduced || timer) { return; }
        timer = window.setInterval(function () { show(current + 1); }, SLIDE_MS);
    }
    function pause() {
        window.clearInterval(timer);
        timer = 0;
    }

    hero.querySelector('[data-prev]').addEventListener('click', function () { pause(); show(current - 1); play(); });
    hero.querySelector('[data-next]').addEventListener('click', function () { pause(); show(current + 1); play(); });
    for (var k = 0; k < dots.length; k++) {
        (function (idx) {
            dots[idx].addEventListener('click', function () { pause(); show(idx); play(); });
        })(k);
    }
    hero.addEventListener('mouseenter', pause);
    hero.addEventListener('mouseleave', play);
    hero.addEventListener('focusin', pause);
    hero.addEventListener('focusout', play);
    document.addEventListener('visibilitychange', function () { document.hidden ? pause() : play(); });

    play();
})();
JS, View::POS_END, 'store-hero'); ?>
<?php endif; ?>
