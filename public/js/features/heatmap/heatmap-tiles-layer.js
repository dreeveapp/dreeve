import L from 'leaflet';
import {fetchJson} from "../../utils";
import {tileBounds} from "./explorer-tiles";

const PANE_NAME = 'heatmapTiles';

export default class HeatmapTilesLayer {
    static ICON = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 3h18v18H3zM3 9h18M3 15h18M9 3v18M15 3v18"/></svg>';

    constructor(map, url, color) {
        this.map = map;
        this.url = url;
        this.enabled = false;
        this.activeActivityIds = [];
        this.tilesPerActivity = null;
        this.loadPromise = null;

        const pane = this.map.createPane(PANE_NAME);
        pane.style.zIndex = 390;
        pane.style.pointerEvents = 'none';

        this.visitedTilesLayer = L.polygon([], {
            renderer: L.canvas({pane: PANE_NAME, padding: 0.2}),
            interactive: false,
            fillColor: color,
            fillOpacity: 0.2,
            color: color,
            opacity: 0.5,
            weight: 1,
        });
    }

    setActiveActivityIds(activityIds) {
        this.activeActivityIds = activityIds;
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
            .then(tilesPerActivity => {
                this.tilesPerActivity = tilesPerActivity;
            })
            .catch(error => {
                this.loadPromise = null;
                throw error;
            });

        return this.loadPromise;
    }

    _sync() {
        if (!this.enabled || !this.tilesPerActivity) {
            this.map.removeLayer(this.visitedTilesLayer);
            return;
        }

        const visitedTiles = new Set();
        this.activeActivityIds.forEach(activityId => {
            (this.tilesPerActivity[activityId] || []).forEach(tile => visitedTiles.add(tile));
        });

        this.visitedTilesLayer.setLatLngs([...visitedTiles].map(tile => {
            const [[south, west], [north, east]] = tileBounds(tile);
            return [[[north, west], [north, east], [south, east], [south, west]]];
        }));
        this.visitedTilesLayer.addTo(this.map);
    }
}
