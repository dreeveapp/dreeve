const TILE_LAYER_URL = 'https://tile.openstreetmap.org/{z}/{x}/{y}.png';
const ATTRIBUTION = '© <a href="https://www.openstreetmap.org/copyright" rel="noreferrer noopener">OpenStreetMap</a>';
export const MAX_ZOOM = 17;

export async function createAdminLeafletMap(node, options = {}) {
    const {default: L} = await import(/* webpackChunkName: "leaflet" */ 'leaflet');
    await import(/* webpackChunkName: "leaflet" */ './ctrl-scroll-zoom');

    const map = L.map(node, {
        ctrlScrollZoom: true,
        maxZoom: MAX_ZOOM,
        zoomSnap: .5,
        zoomDelta: .5,
        ...options,
    });
    L.tileLayer(TILE_LAYER_URL, {attribution: ATTRIBUTION, maxZoom: MAX_ZOOM}).addTo(map);

    return {L, map};
}
