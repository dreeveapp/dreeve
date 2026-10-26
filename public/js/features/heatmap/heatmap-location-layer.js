import L from 'leaflet';
import {showToast} from "../../components/toast";

const MIN_FLY_TO_ZOOM = 15;
const TIMEOUT_IN_MS = 30000;
const PERMISSION_DENIED = 1;
const DOT_COLOR = '#2563eb';

export default class HeatmapLocationLayer {
    static ICON = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5.36328 12.0523C4.01081 11.5711 3.33457 11.3304 3.13309 10.9655C2.95849 10.6492 2.95032 10.2673 3.11124 9.94388C3.29694 9.57063 3.96228 9.30132 5.29295 8.76272L17.8356 3.68594C19.1461 3.15547 19.8014 2.89024 20.2154 3.02623C20.5747 3.14427 20.8565 3.42608 20.9746 3.7854C21.1106 4.19937 20.8453 4.85465 20.3149 6.16521L15.2381 18.7078C14.6995 20.0385 14.4302 20.7039 14.0569 20.8896C13.7335 21.0505 13.3516 21.0423 13.0353 20.8677C12.6704 20.6662 12.4297 19.99 11.9485 18.6375L10.4751 14.4967C10.3815 14.2336 10.3347 14.102 10.2582 13.9922C10.1905 13.8948 10.106 13.8103 10.0086 13.7426C9.89876 13.6661 9.76719 13.6193 9.50407 13.5257L5.36328 12.0523Z"/></svg>';

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
            this.dot = L.marker(e.latlng, {
                icon: L.divIcon({className: 'leaflet-location-dot', iconSize: [16, 16]}),
                interactive: false,
                keyboard: false,
            }).addTo(this.featureGroup);
            this.featureGroup.addTo(this.map);
            return;
        }

        this.accuracyCircle.setLatLng(e.latlng).setRadius(e.accuracy);
        this.dot.setLatLng(e.latlng);
    }
}
