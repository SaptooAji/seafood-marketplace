/*
 * The store map loads Leaflet and its tiles only on pages with a map, and only
 * once the map scrolls into view.
 */
const storeMaps = document.querySelectorAll('[data-store-map]');

if (storeMaps.length > 0 && 'IntersectionObserver' in window) {
    const observer = new IntersectionObserver((entries) => {
        for (const entry of entries) {
            if (!entry.isIntersecting) {
                continue;
            }

            observer.unobserve(entry.target);
            import('./store-map.js').then(({ mountStoreMap }) => mountStoreMap(entry.target));
        }
    }, { rootMargin: '200px' });

    storeMaps.forEach((map) => observer.observe(map));
}
