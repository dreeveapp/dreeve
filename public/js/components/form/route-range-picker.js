import {fetchJson} from "../../utils";
import {END_MARKER_COLOR, MARKER_BORDER_COLOR, START_MARKER_COLOR} from "../../features/maps/route-marker-colors";

const ROOT_SELECTOR = '[data-route-range-picker]';
const MAP_SELECTOR = '[data-route-range-picker-map]';
const TILE_LAYER_URL = 'https://tile.openstreetmap.org/{z}/{x}/{y}.png';
const ATTRIBUTION = '© <a href="https://www.openstreetmap.org/copyright" rel="noreferrer noopener">OpenStreetMap</a>';
const MAX_ZOOM = 17;
const ROUTE_COLOR = '#9CA3AF';
const SELECTION_COLOR = '#F26722';

const markerIcon = (L, fillColor) => L.divIcon({
    className: '',
    html: `<div class="size-4.5 rounded-full border-3 cursor-grab" style="background-color: ${fillColor}; border-color: ${MARKER_BORDER_COLOR}"></div>`,
    iconSize: [18, 18],
    iconAnchor: [9, 9],
});

class RouteRangePicker {
    constructor(root) {
        this.root = root;
        this.options = JSON.parse(root.getAttribute('data-route-range-picker') || '{}');
        this.source = this.options.source ? document.querySelector(this.options.source) : null;
        this.mapNode = root.querySelector(MAP_SELECTOR);
        this.fields = {
            start: root.querySelector('[data-route-range-picker-field="startIndex"]'),
            end: root.querySelector('[data-route-range-picker-field="endIndex"]'),
        };
        this.outputs = {
            distance: root.querySelector('[data-route-range-picker-output="distance"]'),
            elevationGain: root.querySelector('[data-route-range-picker-output="elevationGain"]'),
        };

        this.route = null;
        this.map = null;
        this.layers = null;
        this.requestId = 0;
        this.loadedValue = null;
    }

    init() {
        if (!this.source || !this.mapNode || !this.fields.start || !this.fields.end) {
            return;
        }

        this.source.addEventListener('change', () => this.load());

        if ('' !== this.source.value.trim()) {
            this.load();
        }
    }

    setState(state) {
        this.root.dataset.state = state;
    }

    async load() {
        const value = this.source.value.trim();
        if (value === this.loadedValue) {
            return;
        }
        this.loadedValue = value;
        const requestId = ++this.requestId;

        if ('' === value) {
            this.route = null;
            this.setState('empty');

            return;
        }

        this.setState('loading');

        let route = null;
        try {
            route = await fetchJson(this.options.url.replace(this.options.placeholder, encodeURIComponent(value)));
        } catch {
            route = null;
        }

        if (requestId !== this.requestId) {
            return;
        }
        if (!route || !Array.isArray(route.points) || route.points.length < 2) {
            this.route = null;
            this.setState('error');

            return;
        }

        this.route = route;
        this.fields.start.value = '0';
        this.fields.end.value = String(route.points.length - 1);

        this.setState('ready');
        await this.mount();
        this.renderRoute();
        this.render();
    }

    async mount() {
        if (this.map) {
            this.map.invalidateSize();

            return;
        }

        const {default: L} = await import(/* webpackChunkName: "leaflet" */ 'leaflet');
        await import(/* webpackChunkName: "leaflet" */ '../../features/maps/ctrl-scroll-zoom');

        this.L = L;
        this.map = L.map(this.mapNode, {
            ctrlScrollZoom: true,
            maxZoom: MAX_ZOOM,
            zoomSnap: .5,
            zoomDelta: .5,
        });
        L.tileLayer(TILE_LAYER_URL, {attribution: ATTRIBUTION, maxZoom: MAX_ZOOM}).addTo(this.map);
    }

    renderRoute() {
        if (!this.map) {
            return;
        }

        if (this.layers) {
            Object.values(this.layers).forEach((layer) => layer.remove());
        }

        const L = this.L;
        const points = this.route.points;
        this.layers = {
            route: L.polyline(points, {color: ROUTE_COLOR, weight: 4, interactive: false}).addTo(this.map),
            selection: L.polyline([], {color: SELECTION_COLOR, weight: 6, interactive: false}).addTo(this.map),
            start: L.marker(points[0], {draggable: true, icon: markerIcon(L, START_MARKER_COLOR)}).addTo(this.map),
            end: L.marker(points[points.length - 1], {draggable: true, icon: markerIcon(L, END_MARKER_COLOR)}).addTo(this.map),
        };

        ['start', 'end'].forEach((boundary) => {
            this.layers[boundary].on('drag', () => this.writeIndex(boundary, this.nearestIndex(this.layers[boundary].getLatLng())));
            this.layers[boundary].on('dragend', () => this.render());
        });

        this.map.fitBounds(this.layers.route.getBounds(), {padding: [16, 16]});
    }

    index(boundary) {
        return parseInt(this.fields[boundary].value, 10) || 0;
    }

    writeIndex(boundary, index) {
        if (!this.route) {
            return;
        }

        const lastIndex = this.route.points.length - 1;
        const clamped = 'start' === boundary
            ? Math.min(Math.max(index, 0), this.index('end') - 1)
            : Math.max(Math.min(index, lastIndex), this.index('start') + 1);

        this.fields[boundary].value = String(clamped);
        this.render();
    }

    nearestIndex({lat, lng}) {
        let nearestIndex = 0;
        let nearestDistance = Infinity;
        this.route.points.forEach(([pointLat, pointLng], index) => {
            const distance = (pointLat - lat) ** 2 + (pointLng - lng) ** 2;
            if (distance < nearestDistance) {
                nearestDistance = distance;
                nearestIndex = index;
            }
        });

        return nearestIndex;
    }

    render() {
        if (!this.route) {
            return;
        }

        const start = this.index('start');
        const end = this.index('end');
        const {points, distance, altitude} = this.route;

        if (this.layers) {
            this.layers.selection.setLatLngs(points.slice(start, end + 1));
            this.layers.start.setLatLng(points[start]);
            this.layers.end.setLatLng(points[end]);
        }

        if (this.outputs.distance) {
            this.outputs.distance.textContent = ((distance[end] - distance[start]) / this.options.distanceInMetersPerUnit).toFixed(2);
        }

        if (this.outputs.elevationGain) {
            if (!Array.isArray(altitude) || altitude.length <= end) {
                this.outputs.elevationGain.textContent = '-';

                return;
            }

            let elevationGain = 0;
            for (let index = start + 1; index <= end; index++) {
                elevationGain += Math.max(0, altitude[index] - altitude[index - 1]);
            }
            this.outputs.elevationGain.textContent = String(Math.round(elevationGain / this.options.elevationInMetersPerUnit));
        }
    }
}

export default function initRouteRangePickers() {
    document.querySelectorAll(ROOT_SELECTOR).forEach((root) => new RouteRangePicker(root).init());
}
