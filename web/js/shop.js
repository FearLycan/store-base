// Storefront client store: device-local favorites + recently-viewed, persisted
// in localStorage. Registered before Alpine boots (see ShopAsset js order) so
// the alpine:init listener is attached in time.
document.addEventListener('alpine:init', () => {
    const read = (k) => {
        try { const v = JSON.parse(localStorage.getItem(k)); return Array.isArray(v) ? v : []; }
        catch (e) { return []; }
    };
    const write = (k, v) => { try { localStorage.setItem(k, JSON.stringify(v)); } catch (e) {} };

    Alpine.store('shop', {
        favs: read('sb_favs'),
        recent: read('sb_recent'),

        get favCount() { return this.favs.length; },
        isFav(slug) { return this.favs.some((r) => r && r.slug === slug); },

        toggleFav(rec) {
            if (!rec || !rec.slug) { return; }
            const i = this.favs.findIndex((r) => r.slug === rec.slug);
            if (i >= 0) { this.favs.splice(i, 1); }
            else { this.favs.unshift(rec); if (this.favs.length > 100) { this.favs.length = 100; } }
            write('sb_favs', this.favs);
        },

        pushRecent(rec) {
            if (!rec || !rec.slug) { return; }
            this.recent = this.recent.filter((r) => r && r.slug !== rec.slug);
            this.recent.unshift(rec);
            if (this.recent.length > 12) { this.recent.length = 12; }
            write('sb_recent', this.recent);
        },
    });
});
