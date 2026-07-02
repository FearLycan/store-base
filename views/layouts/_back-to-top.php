<?php
/**
 * Floating "back to top" button (bottom-right), shown once the user scrolls past
 * the first screenful. Stays in the DOM so the fade-out can play; smooth scroll
 * only when the OS doesn't ask for reduced motion.
 */
?>
<button type="button"
        x-data="{ show: false }"
        @scroll.window.passive="show = window.scrollY > 600"
        :class="show ? 'is-visible' : ''"
        @click="window.scrollTo({ top: 0, behavior: matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth' })"
        class="back-to-top"
        aria-label="Back to top">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-5 w-5" aria-hidden="true">
        <path d="M12 19V5"/>
        <path d="m5 12 7-7 7 7"/>
    </svg>
</button>
