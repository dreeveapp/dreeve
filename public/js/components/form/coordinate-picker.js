import {eventBus, Events} from '../../core/event-bus';

const ROOT_SELECTOR = '[data-coordinate-picker]';
const MAP_SELECTOR = '[data-coordinate-picker-map]';
const TILE_LAYER_URL = 'https://tile.openstreetmap.org/{z}/{x}/{y}.png';
const ATTRIBUTION = '© <a href="https://www.openstreetmap.org/copyright" rel="noreferrer noopener">OpenStreetMap</a>';
const MIN_ZOOM = 1;
const MAX_ZOOM = 17;
const WORLD_ZOOM = 2;
const COORDINATE_ZOOM = 13;

const instances = new Map();

const numberOf = (field) => {
    const value = parseFloat(field?.value ?? '');

    return Number.isFinite(value) ? value : null;
};

class CoordinatePicker {
    constructor(root) {
        this.root = root;
        this.mapNode = root.querySelector(MAP_SELECTOR);
        this.fields = {
            latitude: root.querySelector('[data-coordinate-picker-field="latitude"]'),
            longitude: root.querySelector('[data-coordinate-picker-field="longitude"]'),
            radius: root.querySelector('[data-coordinate-picker-field="radius"]'),
        };

        const options = JSON.parse(root.getAttribute('data-coordinate-picker') || '{}');
        this.radiusToMeter = options.radiusToMeter || 1;

        this.map = null;
        this.marker = null;
        this.circle = null;
        this.hasCoordinate = false;
        this.isMounting = false;
        this.isDestroyed = false;
        this.isWriting = false;
    }

    init() {
        if (!this.mapNode || !this.fields.latitude || !this.fields.longitude) {
            return;
        }

        ['input', 'change'].forEach((eventName) => {
            this.fields.latitude.addEventListener(eventName, () => this.syncFromFields());
            this.fields.longitude.addEventListener(eventName, () => this.syncFromFields());
            this.fields.radius?.addEventListener(eventName, () => this.syncFromFields());
        });

        this.resizeObserver = new ResizeObserver(() => this.handleResize());
        this.resizeObserver.observe(this.mapNode);
    }

    hasSize() {
        return this.mapNode.offsetWidth > 0 && this.mapNode.offsetHeight > 0;
    }

    handleResize() {
        if (!this.hasSize()) {
            return;
        }

        if (!this.map) {
            this.mount();

            return;
        }

        this.map.invalidateSize();
    }

    async mount() {
        if (this.isMounting || this.map) {
            return;
        }
        this.isMounting = true;

        const {default: L} = await import(/* webpackChunkName: "leaflet" */ 'leaflet');
        await import(/* webpackChunkName: "leaflet" */ '../../features/maps/ctrl-scroll-zoom');

        this.isMounting = false;
        if (this.isDestroyed) {
            return;
        }

        this.L = L;
        this.map = L.map(this.mapNode, {
            ctrlScrollZoom: true,
            minZoom: MIN_ZOOM,
            maxZoom: MAX_ZOOM,
            zoomSnap: .5,
            zoomDelta: .5,
        });
        L.tileLayer(TILE_LAYER_URL, {attribution: ATTRIBUTION, maxZoom: MAX_ZOOM}).addTo(this.map);

        this.map.on('click', (event) => this.writeCoordinate(event.latlng));

        this.resetView();
        this.render();
        this.map.invalidateSize();
    }

    coordinate() {
        const latitude = numberOf(this.fields.latitude);
        const longitude = numberOf(this.fields.longitude);

        if (null === latitude || null === longitude) {
            return null;
        }
        if (Math.abs(latitude) > 90 || Math.abs(longitude) > 180) {
            return null;
        }

        return [latitude, longitude];
    }

    radiusInMeters() {
        const radius = numberOf(this.fields.radius);

        return null !== radius && radius > 0 ? radius * this.radiusToMeter : null;
    }

    writeCoordinate({lat, lng}) {
        this.isWriting = true;
        this.fields.latitude.value = lat.toFixed(6);
        this.fields.longitude.value = lng.toFixed(6);
        [this.fields.latitude, this.fields.longitude].forEach((field) => {
            field.dispatchEvent(new Event('input', {bubbles: true}));
            field.dispatchEvent(new Event('change', {bubbles: true}));
        });
        this.isWriting = false;

        this.render();
    }

    syncFromFields() {
        if (this.isWriting || !this.map) {
            return;
        }

        const hadCoordinate = this.hasCoordinate;
        this.render();

        const coordinate = this.coordinate();
        if (coordinate && !hadCoordinate) {
            this.resetView();

            return;
        }

        if (coordinate && !this.map.getBounds().contains(coordinate)) {
            this.map.panTo(coordinate);
        }
    }

    render() {
        const coordinate = this.coordinate();
        this.hasCoordinate = null !== coordinate;

        if (!coordinate) {
            this.marker?.remove();
            this.circle?.remove();
            this.marker = null;
            this.circle = null;

            return;
        }

        if (!this.marker) {
            this.marker = this.L.marker(coordinate, {
                draggable: true,
                icon: this.L.divIcon({
                    className: '',
                    html: '<div class="h-4 w-4 rounded-full border-2 border-white bg-primary shadow-md"></div>',
                    iconSize: [16, 16],
                    iconAnchor: [8, 8],
                }),
            }).addTo(this.map);
            this.marker.on('drag', () => this.circle?.setLatLng(this.marker.getLatLng()));
            this.marker.on('dragend', () => this.writeCoordinate(this.marker.getLatLng()));
        } else {
            this.marker.setLatLng(coordinate);
        }

        const radius = this.radiusInMeters();
        if (null === radius) {
            this.circle?.remove();
            this.circle = null;

            return;
        }

        if (!this.circle) {
            this.circle = this.L.circle(coordinate, {
                radius,
                color: '#F26722',
                weight: 2,
                fillOpacity: .15,
                interactive: false,
            }).addTo(this.map);

            return;
        }

        this.circle.setLatLng(coordinate);
        this.circle.setRadius(radius);
    }

    resetView() {
        const coordinate = this.coordinate();
        if (!coordinate) {
            this.map.setView([20, 0], WORLD_ZOOM);

            return;
        }

        const radius = this.radiusInMeters();
        if (null !== radius) {
            this.map.fitBounds(this.L.latLng(coordinate).toBounds(radius * 2), {maxZoom: MAX_ZOOM});

            return;
        }

        this.map.setView(coordinate, COORDINATE_ZOOM);
    }

    destroy() {
        this.isDestroyed = true;
        this.resizeObserver?.disconnect();
        this.map?.remove();
        this.map = null;
    }
}

const destroyDetachedPickers = () => {
    instances.forEach((picker, root) => {
        if (document.contains(root)) {
            return;
        }
        picker.destroy();
        instances.delete(root);
    });
};

export default function initCoordinatePickers(rootNode = document) {
    destroyDetachedPickers();

    rootNode.querySelectorAll(ROOT_SELECTOR).forEach((root) => {
        if (instances.has(root)) {
            return;
        }

        const picker = new CoordinatePicker(root);
        instances.set(root, picker);
        picker.init();
    });
}

eventBus.on(Events.REPEATER_CHANGED, ({repeater}) => initCoordinatePickers(repeater));
