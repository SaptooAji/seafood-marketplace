import L from 'leaflet';
import 'leaflet/dist/leaflet.css';

/*
 * The map on the store page (#214): a still Leaflet map centred on the store,
 * with our own pin. Tiles come from one place so they can move to another
 * provider before production; the CSP in Tahap 7 must allow this host in
 * img-src (CONF-5).
 */
const TILE_URL = 'https://tile.openstreetmap.org/{z}/{x}/{y}.png';
const ATTRIBUTION = '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>';
const ZOOM = 15;

/*
 * A green pin holding the marketplace logo from the header: a rounded white
 * square with the fish and the orange dot.
 */
const PIN = `<svg xmlns="http://www.w3.org/2000/svg" width="48" height="60" viewBox="0 0 48 60" aria-hidden="true" focusable="false">
    <path d="M24 58s-20-17.5-20-34a20 20 0 0 1 40 0c0 16.5-20 34-20 34z" fill="#2F5D46" stroke="#FFFFFF" stroke-width="2.5"/>
    <g transform="translate(9 9) scale(0.47)">
        <path d="M22 4H42A18 18 0 0 1 60 22V42A18 18 0 0 1 42 60H22A18 18 0 0 1 4 42V22A18 18 0 0 1 22 4Z" fill="#FFFFFF" stroke="#1B2E25" stroke-width="3"/>
        <path d="M14 33C19 26 26 24 34 26L41 21V45L34 40C26 42 19 40 14 33Z" fill="none" stroke="#1B2E25" stroke-width="3" stroke-linejoin="round"/>
        <circle cx="48" cy="15" r="6" fill="#D9733C"/>
    </g>
</svg>`;

/**
 * Draw the map over the drawn fallback inside the given element. Coordinates
 * that are not numbers leave the fallback in place.
 *
 * @param {HTMLElement} element an element with data-lat and data-lng
 */
export function mountStoreMap(element) {
    const lat = Number(element.dataset.lat);
    const lng = Number(element.dataset.lng);

    if (element.dataset.lat === '' || element.dataset.lng === '' || !Number.isFinite(lat) || !Number.isFinite(lng) || Math.abs(lat) > 90 || Math.abs(lng) > 180) {
        return;
    }

    const canvas = document.createElement('div');
    canvas.className = 'absolute inset-0';
    element.append(canvas);

    const map = L.map(canvas, {
        dragging: false,
        scrollWheelZoom: false,
        doubleClickZoom: false,
        touchZoom: false,
        boxZoom: false,
        keyboard: false,
        zoomControl: false,
    }).setView([lat, lng], ZOOM);

    map.attributionControl.setPrefix(false);
    L.tileLayer(TILE_URL, { attribution: ATTRIBUTION, maxZoom: 19 }).addTo(map);

    L.marker([lat, lng], {
        icon: L.divIcon({ html: PIN, className: '', iconSize: [48, 60], iconAnchor: [24, 58] }),
        interactive: false,
        keyboard: false,
    }).addTo(map);
}
