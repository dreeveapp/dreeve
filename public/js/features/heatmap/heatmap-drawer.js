import {pointToLineDistance, point, lineString} from "../../../libraries/turf";
import L from 'leaflet';
import {createFlyToPlacesControl, createMapToolsControl} from "../maps/leaflet-controls";
import HeatmapCountriesLayer from "./heatmap-countries-layer";
import HeatmapTilesLayer from "./heatmap-tiles-layer";
import HeatmapLocationLayer from "./heatmap-location-layer";
import '../maps/ctrl-scroll-zoom';

export default class HeatmapDrawer {
    static CLICK_TOLERANCE_IN_PIXELS = 10;
    static MIN_RADIUS_IN_METERS = 15;
    static MAX_RADIUS_IN_METERS = 100;
    static BAND_IN_METERS = 15;

    constructor(wrapper, config) {
        this.wrapper = wrapper;
        this.config = config;
        this.placesControl = null;
        this.mapToolsControl = null;
        this.mainFeatureGroup = L.featureGroup();
        this.routesVisible = true;
        this.routePolylines = [];
        this.map = L.map(this.wrapper, {
            ctrlScrollZoom: this.config.requireCtrlToZoom,
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

        const countriesUrl = this.wrapper.getAttribute('data-leaflet-countries');
        this.translations = JSON.parse(this.wrapper.getAttribute('data-translations'));
        this.countriesLayer = countriesUrl
            ? new HeatmapCountriesLayer(this.map, countriesUrl, this.config.polylineColor)
            : null;
        this.filtersNode = this.wrapper.closest('.heatmap-wrapper')?.querySelector('[data-heatmap-filters]');
        const tilesUrl = this.wrapper.getAttribute('data-leaflet-tiles');
        this.tilesLayer = tilesUrl
            ? new HeatmapTilesLayer(
                this.map,
                tilesUrl,
                this.config.polylineColor,
                this.wrapper.closest('.heatmap-wrapper')?.querySelector('[data-heatmap-tiles-stats]'),
            )
            : null;
        this.locationLayer = HeatmapLocationLayer.isSupported()
            ? new HeatmapLocationLayer(this.map, {
                blocked: this.translations.locationBlocked,
                unavailable: this.translations.locationUnavailable,
            })
            : null;

        this.map.on("click", (e) => this._handleMapClick(e));
        this.map.on("popupclose", () => this._resetRouteStyles());
    }

    _setFiltersDisabled(disabled) {
        if (this.filtersNode) this.filtersNode.disabled = disabled;
    }

    _setRoutesVisible(visible) {
        this.routesVisible = visible;
        if (visible) {
            this.mainFeatureGroup.addTo(this.map);
            return;
        }

        this.map.closePopup();
        this.map.removeLayer(this.mainFeatureGroup);
    }

    _resetRouteStyles() {
        this.routePolylines.forEach(entry => {
            entry.polyline.setStyle(this.defaultPolylineStyle);
        });
    }

    _handleMapClick(e) {
        if (!this.routesVisible) {
            return;
        }

        const clickPoint = point([e.latlng.lng, e.latlng.lat]);
        const containerPoint = this.map.latLngToContainerPoint(e.latlng);
        const metersPerPixel = this.map.distance(e.latlng, this.map.containerPointToLatLng(containerPoint.add([1, 0])));
        const radius = Math.min(
            HeatmapDrawer.MAX_RADIUS_IN_METERS,
            Math.max(HeatmapDrawer.MIN_RADIUS_IN_METERS, HeatmapDrawer.CLICK_TOLERANCE_IN_PIXELS * metersPerPixel)
        );
        this._resetRouteStyles();

        const searchBounds = e.latlng.toBounds((radius + HeatmapDrawer.BAND_IN_METERS) * 2);
        const distances = this.routePolylines.map((entry) => {
            if (!entry.line || !searchBounds.intersects(entry.bounds)) {
                return Infinity;
            }

            return pointToLineDistance(clickPoint, entry.line, {units: "meters"});
        });

        const closestDistance = Math.min(...distances);
        if (closestDistance > radius) {
            return;
        }

        const maxDistance = closestDistance + HeatmapDrawer.BAND_IN_METERS;
        const nearby = this.routePolylines.filter((entry, index) => distances[index] <= maxDistance);

        this.routePolylines
            .filter((entry, index) => distances[index] > maxDistance)
            .forEach(entry => entry.polyline.setStyle(this.inactivePolylineStyle));

        const html = `
            <div class="m-4 text-sm max-h-50 overflow-y-auto no-dark">
                <div class="font-medium">${this.translations.nearbyRoutes.replace('{numberOfRoutes}', nearby.length)}</div>
                 <ul class="divide-default divide-y divide-gray-200">
                    ${nearby.map(entry => `
                     <li class="py-2">
                      <a href="${entry.route.activityUrl}" title="${entry.route.name}" class="block truncate font-medium text-blue-600 hover:underline">
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
                bounds: polyline.getBounds(),
                line: route.coordinates.length >= 2 ? lineString(route.coordinates.map(ll => [ll[1], ll[0]])) : null
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
        this.countriesLayer?.setActiveCountryCodes([...countryBounds.keys()]);
        if (this.routesVisible) {
            this.mainFeatureGroup.addTo(this.map);
        }

        this.placesControl = createFlyToPlacesControl({places});
        this.map.addControl(this.placesControl);

        if (!this.mapToolsControl) {
            this.mapToolsControl = createMapToolsControl({
                showReset: false,
                toggles: [
                    this.countriesLayer ? {
                        icon: HeatmapCountriesLayer.ICON,
                        title: this.translations.highlightCountries,
                        onToggle: (active) => this.countriesLayer.setEnabled(active),
                    } : null,
                    this.tilesLayer ? {
                        icon: HeatmapTilesLayer.ICON,
                        title: this.translations.highlightTiles,
                        onToggle: (active) => {
                            this._setRoutesVisible(!active);
                            this._setFiltersDisabled(active);
                            return this.tilesLayer.setEnabled(active).catch(error => {
                                this._setRoutesVisible(true);
                                this._setFiltersDisabled(false);
                                throw error;
                            });
                        },
                    } : null,
                    this.locationLayer ? {
                        icon: HeatmapLocationLayer.ICON,
                        title: this.translations.showLocation,
                        onToggle: (active) => this.locationLayer.setEnabled(active),
                    } : null,
                ].filter(Boolean),
            });
            this.map.addControl(this.mapToolsControl);
        }

        if (this.config.initialCenter && this.config.initialZoom) {
            this.map.setView(this.config.initialCenter, this.config.initialZoom);
        } else if (fitMapBoundsFeatureGroup.getBounds().isValid()) {
            this.map.fitBounds(fitMapBoundsFeatureGroup.getBounds());
        }
    }
}
