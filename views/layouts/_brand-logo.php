<?php

/**
 * snagloft brand logo (icon + wordmark), inline SVG from the hub brand pack.
 * The icon's hook and the "loft" half of the wordmark take the store's accent
 * colour (var(--accent)), so each deployment tints the logo automatically.
 *
 * @var yii\web\View $this
 * @var int $iconSize       icon square, px; 0 omits the icon (hub masthead: 30, footer: 42)
 * @var int $wordmarkHeight wordmark height, px; 0 omits the wordmark (hub masthead: 38, footer: 42)
 */

$iconSize = (int)($iconSize ?? 30);
$wordmarkHeight = (int)($wordmarkHeight ?? 38);
?>
<?php if ($iconSize > 0): ?>
<svg class="brand-icon" style="height:<?= $iconSize ?>px;width:<?= $iconSize ?>px" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100" aria-hidden="true">
    <rect width="100" height="100" rx="22" fill="var(--brand-ink)"/>
    <path d="M28 50 L50 18 L72 50" fill="none" stroke="var(--brand-cream)" stroke-width="9" stroke-linecap="round" stroke-linejoin="round"/>
    <path d="M50 50 L50 72 C50 84 64 86 72 77" fill="none" stroke="var(--accent)" stroke-width="9" stroke-linecap="round"/>
</svg>
<?php endif; ?>
<?php if ($wordmarkHeight > 0): ?>
<svg class="brand-wordmark" style="height:<?= $wordmarkHeight ?>px;width:auto" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 559.1 196.0" role="img" aria-label="snagloft">
    <g transform="translate(42.48,128.0) scale(0.12,-0.12)">
        <g fill="var(--brand-ink)">
            <path transform="translate(0.0,0)" d="M278 -14C401 -14 481 43 481 142C481 242 396 274 289 293L254 299C200 309 167 324 167 364C167 401 200 423 257 423C315 423 356 401 367 341L463 368C442 454 368 507 257 507C142 507 67 453 67 360C67 267 147 228 247 210L282 204C346 192 380 177 380 137C380 96 343 69 278 69C212 69 156 95 142 178L46 155C64 42 152 -14 278 -14Z"/>
            <path transform="translate(500.0,0)" d="M76 0H179V249C179 355 235 413 320 413C395 413 440 374 440 288V0H543V296C543 423 462 501 350 501C255 501 212 459 193 419H177V493H76Z"/>
            <path transform="translate(1090.0,0)" d="M229 -14C331 -14 367 38 382 70H398V67C398 26 430 0 476 0H553V86H511C493 86 483 96 483 116V319C483 439 405 507 277 507C151 507 86 440 62 362L158 331C170 384 205 421 276 421C348 421 382 383 382 326V294H232C124 294 44 242 44 142C44 42 124 -14 229 -14ZM244 71C183 71 147 100 147 145C147 190 183 214 238 214H382V204C382 121 326 71 244 71Z"/>
            <path transform="translate(1642.0,0)" d="M52 244C52 90 156 -3 281 -3C378 -3 424 39 446 77H462V-80C462 -100 452 -110 433 -110H133V-200H465C526 -200 564 -162 564 -101V493H464V422H448C424 463 377 507 281 507C156 507 52 414 52 259ZM309 87C221 87 156 146 156 247V256C156 357 220 416 309 416C399 416 463 357 463 256V247C463 146 398 87 309 87Z"/>
        </g>
        <g fill="var(--accent)">
            <path transform="translate(2257.0,0)" d="M76 0H179V700H76Z"/>
            <path transform="translate(2488.0,0)" d="M307 -14C455 -14 563 82 563 239V254C563 410 455 507 307 507C159 507 52 410 52 254V239C52 82 159 -14 307 -14ZM307 78C217 78 155 139 155 242V251C155 353 217 415 307 415C398 415 460 353 460 251V242C460 139 397 78 307 78Z"/>
            <path transform="translate(3078.0,0)" d="M165 0H268V406H410V493H268V584C268 604 278 614 297 614H391V700H264C203 700 165 662 165 601V493H27V406H165Z"/>
            <path transform="translate(3492.0,0)" d="M261 0H397V87H294C275 87 266 97 266 117V406H413V493H266V656H163V493H27V406H163V99C163 38 200 0 261 0Z"/>
        </g>
    </g>
</svg>
<?php endif; ?>
