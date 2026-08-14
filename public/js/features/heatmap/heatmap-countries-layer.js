import L from 'leaflet';
import {fetchJson} from "../../utils";

const PANE_NAME = 'heatmapCountries';

export default class HeatmapCountriesLayer {
    static ICON = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="14" height="14" fill="currentColor"><path fill-rule="evenodd" clip-rule="evenodd" d="M8.64 4.737A7.97 7.97 0 0 1 12 4a7.997 7.997 0 0 1 6.933 4.006h-.738c-.65 0-1.177.25-1.177.9 0 .33 0 2.04-2.026 2.008-1.972 0-1.972-1.732-1.972-2.008 0-1.429-.787-1.65-1.752-1.923-.374-.105-.774-.218-1.166-.411-1.004-.497-1.347-1.183-1.461-1.835ZM6 4a10.06 10.06 0 0 0-2.812 3.27A9.956 9.956 0 0 0 2 12c0 5.289 4.106 9.619 9.304 9.976l.054.004a10.12 10.12 0 0 0 1.155.007h.002a10.024 10.024 0 0 0 1.5-.19 9.925 9.925 0 0 0 2.259-.754 10.041 10.041 0 0 0 4.987-5.263A9.917 9.917 0 0 0 22 12a10.025 10.025 0 0 0-.315-2.5A10.001 10.001 0 0 0 12 2a9.964 9.964 0 0 0-6 2Zm13.372 11.113a2.575 2.575 0 0 0-.75-.112h-.217A3.405 3.405 0 0 0 15 18.405v1.014a8.027 8.027 0 0 0 4.372-4.307ZM12.114 20H12A8 8 0 0 1 5.1 7.95c.95.541 1.421 1.537 1.835 2.415.209.441.403.853.637 1.162.54.712 1.063 1.019 1.591 1.328.52.305 1.047.613 1.6 1.316 1.44 1.825 1.419 4.366 1.35 5.828Z"/></svg>';

    constructor(map, url, color) {
        this.map = map;
        this.url = url;
        this.enabled = false;
        this.activeCountryCodes = new Set();
        this.layersByCountryCode = new Map();
        this.featuresByCountryCode = null;
        this.loadPromise = null;

        const pane = this.map.createPane(PANE_NAME);
        pane.style.zIndex = 380;
        pane.style.pointerEvents = 'none';

        this.layerOptions = {
            renderer: L.canvas({pane: PANE_NAME, padding: 0.2}),
            interactive: false,
            fillColor: color,
            fillOpacity: 0.12,
            color: color,
            opacity: 0.6,
            weight: 1.5,
        };
        this.featureGroup = L.featureGroup();
    }

    setActiveCountryCodes(countryCodes) {
        this.activeCountryCodes = new Set(countryCodes.map(countryCode => countryCode.toUpperCase()));
        this._sync();
    }

    async setEnabled(enabled) {
        this.enabled = enabled;
        if (enabled) {
            await this._load();
        }
        this._sync();
    }

    _load() {
        this.loadPromise ??= fetchJson(this.url)
            .then(geoJson => {
                this.featuresByCountryCode = new Map(
                    (geoJson.features || []).map(feature => [feature.properties.countryCode, feature])
                );
            })
            .catch(error => {
                this.loadPromise = null;
                throw error;
            });

        return this.loadPromise;
    }

    _sync() {
        if (!this.enabled || !this.featuresByCountryCode) {
            this.map.removeLayer(this.featureGroup);
            return;
        }

        this.activeCountryCodes.forEach(countryCode => {
            if (this.layersByCountryCode.has(countryCode)) return;
            const feature = this.featuresByCountryCode.get(countryCode);
            if (!feature) return;
            this.layersByCountryCode.set(countryCode, L.geoJSON(feature, this.layerOptions));
        });

        this.layersByCountryCode.forEach((layer, countryCode) => {
            const shouldBeVisible = this.activeCountryCodes.has(countryCode);
            if (shouldBeVisible === this.featureGroup.hasLayer(layer)) return;
            if (shouldBeVisible) {
                this.featureGroup.addLayer(layer);
            } else {
                this.featureGroup.removeLayer(layer);
            }
        });

        this.featureGroup.addTo(this.map);
    }
}
