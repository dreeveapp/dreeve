import {pointToLineDistance, point, lineString} from "../../../libraries/turf";
import L from 'leaflet';
import {createFlyToPlacesControl, createMapToolsControl} from "../maps/leaflet-controls";
import '../maps/ctrl-scroll-zoom';

export default class HeatmapDrawer {
    constructor(wrapper, config, modalManager) {
        this.wrapper = wrapper;
        this.config = config;
        this.modalManager = modalManager;
        this.placesControl = null;
        this.mapToolsControl = null;
        this.mainFeatureGroup = L.featureGroup();
        this.routePolylines = [];
        this.map = L.map(this.wrapper, {
            ctrlScrollZoom: true,
            minZoom: 1,
            maxZoom: 21,
            preferCanvas: true,
        });
        this.config.tileLayerUrls.forEach((tileLayerUrl) => {
            L.tileLayer(tileLayerUrl).addTo(this.map);
        });
        this.defaultPolylineStyle = {
            color: this.config.polylineColor,
            weight: 1.5,
            opacity: 0.5,
            smoothFactor: 1,
            overrideExisting: true,
            detectColors: true,
        };
        this.inactivePolylineStyle = {
            weight: 0,
            opacity: 0,
        }
        this.map.on("click", (e) => this._handleMapClick(e));
        this.map.on("popupclose", () => this._resetRouteStyles());
        this.map.on("popupopen", (e) => this._handlePopupOpen(e));
    }

    _resetRouteStyles() {
        this.routePolylines.forEach(entry => {
            entry.polyline.setStyle(this.defaultPolylineStyle);
        });
    }

    _handlePopupOpen(e) {
        const container = e.popup.getElement();
        if (!container) return;

        container.querySelectorAll('a[data-model-content-url]').forEach(node => {
            node.addEventListener("click", (e) => {
                e.preventDefault();
                e.stopPropagation();
                this.modalManager.open(node.getAttribute('data-model-content-url'));
            });
        });
    };

    _handleMapClick(e) {
        const clickPoint = point([e.latlng.lng, e.latlng.lat]);
        const NEARBY_DISTANCE_IN_METERS = 100;
        this._resetRouteStyles();

        const nearby = [];
        const notNearby = [];

        this.routePolylines.forEach((entry) => {
            const line = lineString(entry.coordinates.map(ll => [ll[1], ll[0]]));
            const dist = pointToLineDistance(clickPoint, line, {units: "meters"});

            if (dist <= NEARBY_DISTANCE_IN_METERS) {
                nearby.push(entry);
            } else {
                notNearby.push(entry);
            }
        });

        if (nearby.length === 0) {
            return;
        }

        notNearby.forEach(entry => {
            entry.polyline.setStyle(this.inactivePolylineStyle);
        });

        const html = `
            <div class="m-4 text-sm max-h-50 overflow-y-auto no-dark">
                <div class="font-medium">${nearby.length} nearby route(s):</div>
                 <ul class="divide-default divide-y divide-gray-200">
                    ${nearby.map(entry => `
                     <li class="py-2">
                      <a href="#" title="${entry.route.name}" class="block truncate font-medium text-blue-600 hover:underline" 
                        data-model-content-url="${entry.route.activityUrl}">
                        ${entry.route.name}
                      </a>
                      <div class="flex items-center justify-between text-xs text-gray-500">
                        <div>${entry.route.startDate}</div>
                        <div>${entry.route.distance}</div>
                      </div>
                    </li>`).join("")}
                </ul>
            </div>`;

        L.popup(e.latlng,
            {
                content: html,
                maxWidth: 300,
                minWidth: 300
            }).openOn(this.map);
    }

    redraw(routes) {
        routes = routes.filter((route) => route.active);
        this.mainFeatureGroup.clearLayers();
        this.routePolylines = [];
        if (this.placesControl) {
            this.map.removeControl(this.placesControl);
        }

        const determineMostActiveState = (routes) => {
            const stateCounts = routes.reduce((counts, route) => {
                const state = route.startLocation.state;
                if (state) counts[state] = (counts[state] || 0) + 1;
                return counts;
            }, {});

            const mostActiveState = Object.keys(stateCounts).reduce((a, b) => stateCounts[a] > stateCounts[b] ? a : b, '');
            return mostActiveState ? mostActiveState : null;
        };

        const places = [];
        const countryBounds = new Map();
        const fitMapBoundsFeatureGroup = L.featureGroup();
        const mostActiveState = determineMostActiveState(routes);

        routes.forEach(route => {
            const polyline = L.polyline(
                route.coordinates,
                {...this.defaultPolylineStyle, smoothFactor: 2.0}
            ).addTo(this.mainFeatureGroup);

            this.routePolylines.push({
                route: route,
                polyline: polyline,
                coordinates: route.coordinates
            });

            // A route can pass through multiple countries, it needs to be included in each of them.
            (route.countryCodes || []).forEach(countryCode => {
                if (!countryBounds.has(countryCode)) {
                    countryBounds.set(countryCode, L.latLngBounds([]));
                }
                countryBounds.get(countryCode).extend(polyline.getBounds());
            });

            if (mostActiveState === route.startLocation.state) {
                L.polyline(route.coordinates).addTo(fitMapBoundsFeatureGroup);
            }
        });

        countryBounds.forEach((bounds, countryCode) => {
            places.push({
                countryCode: countryCode,
                bounds: bounds
            });
        });
        this.mainFeatureGroup.addTo(this.map);

        this.placesControl = createFlyToPlacesControl({places});
        this.map.addControl(this.placesControl);

        if (!this.mapToolsControl) {
            this.mapToolsControl = createMapToolsControl({showReset: false});
            this.map.addControl(this.mapToolsControl);
        }

        if (this.config.initialCenter && this.config.initialZoom) {
            this.map.setView(this.config.initialCenter, this.config.initialZoom);
        } else if (fitMapBoundsFeatureGroup.getBounds().isValid()) {
            this.map.fitBounds(fitMapBoundsFeatureGroup.getBounds());
        }
    }
}
