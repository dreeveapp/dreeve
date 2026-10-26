import L from 'leaflet';
import {showToast} from "../../components/toast";

const MIN_FLY_TO_ZOOM = 15;
const TIMEOUT_IN_MS = 30000;
const PERMISSION_DENIED = 1;
const DOT_COLOR = '#2563eb';

export default class HeatmapLocationLayer {
    static ICON = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="3 11 22 2 13 21 11 13 3 11"/></svg>';

    static isSupported() {
        return window.isSecureContext && 'geolocation' in navigator;
    }

    constructor(map, errorMessages) {
        this.map = map;
        this.errorMessages = errorMessages;
        this.featureGroup = L.featureGroup();
        this.accuracyCircle = null;
        this.dot = null;
        this._onLocationFound = (e) => this._update(e);
    }

    setEnabled(enabled) {
        if (!enabled) {
            this.map.stopLocate();
            this.map.off('locationfound', this._onLocationFound);
            this.map.removeLayer(this.featureGroup);
            this.featureGroup.clearLayers();
            this.accuracyCircle = null;
            this.dot = null;
            return Promise.resolve();
        }

        return new Promise((resolve, reject) => {
            const onFirstFound = (e) => {
                this.map.off('locationerror', onError);
                this.map.flyTo(e.latlng, Math.max(this.map.getZoom(), MIN_FLY_TO_ZOOM));
                resolve();
            };
            const onError = (e) => {
                this.map.off('locationfound', onFirstFound);
                this.setEnabled(false);
                showToast('error', e.code === PERMISSION_DENIED ? this.errorMessages.blocked : this.errorMessages.unavailable);
                reject(e);
            };
            this.map.once('locationfound', onFirstFound);
            this.map.once('locationerror', onError);
            this.map.on('locationfound', this._onLocationFound);
            this.map.locate({watch: true, enableHighAccuracy: true, setView: false, timeout: TIMEOUT_IN_MS});
        });
    }

    _update(e) {
        if (!this.dot) {
            this.accuracyCircle = L.circle(e.latlng, {
                radius: e.accuracy,
                interactive: false,
                color: DOT_COLOR,
                weight: 1,
                opacity: 0.4,
                fillColor: DOT_COLOR,
                fillOpacity: 0.1,
            }).addTo(this.featureGroup);
            this.dot = L.circleMarker(e.latlng, {
                radius: 7,
                interactive: false,
                color: '#ffffff',
                weight: 2,
                opacity: 1,
                fillColor: DOT_COLOR,
                fillOpacity: 1,
            }).addTo(this.featureGroup);
            this.featureGroup.addTo(this.map);
            return;
        }

        this.accuracyCircle.setLatLng(e.latlng).setRadius(e.accuracy);
        this.dot.setLatLng(e.latlng);
    }
}
