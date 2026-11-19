import {eventBus, Events} from '../../core/event-bus';
import {initComponents} from '../../core/component-registry';
import {fetchJson} from '../../utils';
import {createAdminLeafletMap, MAX_ZOOM} from '../../features/maps/admin-leaflet-map';

const ROOT_SELECTOR = '[data-coordinate-picker]';
const MAP_SELECTOR = '[data-coordinate-picker-map]';
const WARNING_SELECTOR = '[data-coordinate-picker-warning]';
const COLOR = '#F26722';
const MIN_ZOOM = 1;
const WORLD_ZOOM = 2;
const COORDINATE_ZOOM = 13;

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
        this.polylineUrl = options.polylineUrl || null;
        this.maxDistanceFromRouteInMeter = options.maxDistanceFromRouteInMeter || null;
        this.warning = root.querySelector(WARNING_SELECTOR);

        this.map = null;
        this.marker = null;
        this.circle = null;
        this.routes = null;
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

        const {L, map} = await createAdminLeafletMap(this.mapNode, {minZoom: MIN_ZOOM});

        this.isMounting = false;
        if (this.isDestroyed) {
            map.remove();

            return;
        }

        this.L = L;
        this.map = map;

        this.map.on('click', (event) => this.writeCoordinate(event.latlng));

        this.resetView();
        this.render();
        this.map.invalidateSize();

        await this.addRoutes();
    }

    async addRoutes() {
        if (!this.polylineUrl) {
            return;
        }

        const polylines = (await fetchJson(this.polylineUrl)).filter((coordinates) => coordinates.length > 0);
        if (!this.map || 0 === polylines.length) {
            return;
        }

        this.routes = this.L.featureGroup(polylines.map((coordinates) => this.L.polyline(coordinates, {
            color: COLOR,
            weight: 2,
            opacity: .9,
            noClip: true,
            interactive: false,
        }))).addTo(this.map);

        if (!this.coordinate()) {
            this.resetView();
        }
        this.updateWarning(this.coordinate());
    }

    distanceFromRouteInMeter(coordinate) {
        const point = this.map.latLngToLayerPoint(coordinate);

        return Math.min(...this.routes.getLayers()
            .map((polyline) => polyline.closestLayerPoint(point))
            .filter((closestPoint) => null !== closestPoint)
            .map((closestPoint) => this.map.distance(coordinate, this.map.layerPointToLatLng(closestPoint))));
    }

    updateWarning(coordinate) {
        if (!this.warning) {
            return;
        }

        const isFarFromRoute = null !== coordinate
            && null !== this.routes
            && null !== this.maxDistanceFromRouteInMeter
            && this.distanceFromRouteInMeter(coordinate) > this.maxDistanceFromRouteInMeter;

        this.warning.classList.toggle('hidden', !isFarFromRoute);
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
        this.updateWarning(coordinate);

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
            this.marker.on('drag', () => {
                this.circle?.setLatLng(this.marker.getLatLng());
                this.updateWarning(this.marker.getLatLng());
            });
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
                color: COLOR,
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
        if (!coordinate && this.routes) {
            this.map.fitBounds(this.routes.getBounds());

            return;
        }
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

export default function initCoordinatePickers(rootNode = document) {
    initComponents(rootNode, ROOT_SELECTOR, CoordinatePicker);
}

eventBus.on(Events.REPEATER_CHANGED, ({repeater}) => initCoordinatePickers(repeater));
