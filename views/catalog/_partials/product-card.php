<?php
/** @var app\models\Product $product */
use yii\helpers\Html;
use yii\helpers\Url;
use yii\web\View;

$img = $product->main_image ?: '/img/placeholder.png';
$href = Url::to(['/product/view', 'slug' => $product->slug]);
$shotsUrl = Url::to(['/catalog/images', 'id' => $product->id]);

// Hover slideshow, registered once for the whole grid (keyed → deduped).
// On hover-intent we lazily fetch a product's images and cross-fade through
// them over the poster, with a slow Ken-Burns push and capsule progress pills.
$this->registerJs(<<<'JS'
(function () {
    var SLIDE_MS = 3000;   // how long each photo is shown (matches the pill fill)
    var INTENT_MS = 130;   // hover dwell before we bother fetching

    var cards = document.querySelectorAll('[data-shots][data-shots-url]');
    if (!cards.length || !window.fetch) { return; }

    cards.forEach(function (card) {
        var url = card.getAttribute('data-shots-url');
        var shots = null;    // null = not fetched, [] = fetched/empty
        var stage = null, dots = null;
        var current = -1, loading = false, hovering = false, timer = 0, intent = 0;

        function buildStage(images) {
            stage = document.createElement('div');
            stage.className = 'card-stage';
            images.forEach(function (u) {
                var img = document.createElement('img');
                img.className = 'card-shot';
                img.loading = 'lazy';
                img.decoding = 'async';
                img.alt = '';
                img.src = u;
                stage.appendChild(img);
            });
            var header = card.querySelector('img');
            if (header && header.nextSibling) {
                card.insertBefore(stage, header.nextSibling);
            } else {
                card.appendChild(stage);
            }
            if (images.length >= 2) {
                dots = document.createElement('div');
                dots.className = 'card-pills';
                dots.style.setProperty('--slide', SLIDE_MS + 'ms');
                images.forEach(function () {
                    var d = document.createElement('span');
                    d.className = 'card-pill';
                    var fill = document.createElement('i');
                    fill.className = 'card-pill-fill';
                    d.appendChild(fill);
                    dots.appendChild(d);
                });
                card.appendChild(dots);
            }
        }

        function show(next) {
            var imgs = stage.children;
            var prev = current;
            current = next;
            for (var k = 0; k < imgs.length; k++) {
                if (k === next) {
                    imgs[k].style.zIndex = '2';      // incoming, on top
                    imgs[k].classList.add('is-on');  // fades in + Ken-Burns
                } else if (k === prev) {
                    imgs[k].style.zIndex = '1';      // stays opaque just beneath
                } else {
                    imgs[k].style.zIndex = '0';
                    imgs[k].classList.remove('is-on');
                }
            }
            if (dots) {
                var d = dots.children;
                for (var j = 0; j < d.length; j++) {
                    d[j].classList.toggle('is-on', j === next);
                }
            }
            // Drop the outgoing frame only once the incoming has fully covered it,
            // so the cross-fade never thins out to reveal the poster beneath.
            if (prev >= 0 && prev !== next) {
                window.setTimeout(function () {
                    if (current !== prev && imgs[prev]) { imgs[prev].classList.remove('is-on'); }
                }, 950);
            }
        }

        function start() {
            if (!stage || !stage.children.length) { return; }
            if (dots) { dots.classList.add('is-shown'); }
            current = -1;
            show(0);
            if (stage.children.length < 2) { return; }
            timer = window.setInterval(function () {
                show((current + 1) % stage.children.length);
            }, SLIDE_MS);
        }

        function stop() {
            window.clearInterval(timer);
            timer = 0;
            current = -1;
            if (stage) {
                var imgs = stage.children;
                for (var k = 0; k < imgs.length; k++) {
                    imgs[k].classList.remove('is-on');
                    imgs[k].style.zIndex = '';
                }
            }
            if (dots) {
                dots.classList.remove('is-shown');
                var d = dots.children;
                for (var j = 0; j < d.length; j++) { d[j].classList.remove('is-on'); }
            }
        }

        // Hold the first reveal until the leading image has decoded, so it fades
        // in cleanly instead of popping in part-way through the fade.
        function startWhenReady() {
            if (!stage || !stage.children.length) { return; }
            var first = stage.children[0];
            var go = function () { if (hovering) { start(); } };
            if (first.complete && first.naturalWidth > 0) { go(); }
            else if (first.decode) { first.decode().then(go, go); }
            else { first.addEventListener('load', go, { once: true }); first.addEventListener('error', go, { once: true }); }
        }

        function run() {
            if (shots && shots.length) {
                if (!stage) { buildStage(shots); }
                startWhenReady();
                return;
            }
            if (shots !== null || loading) { return; }
            loading = true;
            fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                .then(function (r) { return r.ok ? r.json() : { images: [] }; })
                .then(function (data) {
                    shots = (data && data.images) || [];
                    loading = false;
                    if (shots.length && hovering) { buildStage(shots); startWhenReady(); }
                })
                .catch(function () { shots = []; loading = false; });
        }

        card.addEventListener('mouseenter', function () { hovering = true; intent = window.setTimeout(run, INTENT_MS); });
        card.addEventListener('mouseleave', function () { hovering = false; window.clearTimeout(intent); stop(); });
    });
})();
JS, View::POS_END, 'card-gallery');
?>
<a href="<?= $href ?>" class="group block overflow-hidden rounded-xl border border-gray-200 bg-white transition hover:shadow-md">
    <div class="relative aspect-square overflow-hidden bg-gray-100" data-shots data-shots-url="<?= Html::encode($shotsUrl) ?>">
        <img src="<?= Html::encode($img) ?>" alt="<?= Html::encode($product->displayName) ?>" loading="lazy"
             class="h-full w-full object-cover transition-transform duration-500 group-hover:scale-[1.04]">
    </div>
    <div class="p-3">
        <h3 class="line-clamp-2 min-h-[2.5rem] text-sm text-gray-800"><?= Html::encode($product->displayName) ?></h3>
        <div class="mt-2"><?= $this->render('price', ['product' => $product]) ?></div>
        <div class="mt-1"><?= $this->render('stars', ['value' => $product->rating_value, 'count' => $product->review_count]) ?></div>
    </div>
</a>
