import L from 'leaflet';
import {fetchJson} from "../../utils";
import {tileBounds, tileKey} from "./explorer-tiles";

const PANE_NAME = 'heatmapTiles';
const TILE_BORDER = {opacity: 0.5, weight: 1};
const STYLES = {
    visited: {...TILE_BORDER, fillOpacity: 0.15},
    cluster: {...TILE_BORDER, fillOpacity: 0.35},
    maxCluster: {...TILE_BORDER, fillOpacity: 0.55},
    maxSquare: {fill: false, opacity: 1, weight: 3},
};

const colorWithOpacity = (color, opacity) => `color-mix(in srgb, ${color} ${Math.round(opacity * 100)}%, transparent)`;

export default class HeatmapTilesLayer {
    static ICON = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 3h18v18H3zM3 9h18M3 15h18M9 3v18M15 3v18"/></svg>';

    constructor(map, url, color, statsNode) {
        this.map = map;
        this.statsNode = statsNode;
        this.url = url;
        this.enabled = false;
        this.tiles = null;
        this.loadPromise = null;

        const pane = this.map.createPane(PANE_NAME);
        pane.style.zIndex = 390;
        pane.style.pointerEvents = 'none';

        const renderer = L.canvas({pane: PANE_NAME, padding: 0.2});
        const layerOptions = (name) => ({
            renderer: renderer,
            interactive: false,
            fillColor: color,
            color: color,
            ...STYLES[name],
        });
        this.visitedTilesLayer = L.polygon([], layerOptions('visited'));
        this.clusterTilesLayer = L.polygon([], layerOptions('cluster'));
        this.maxClusterTilesLayer = L.polygon([], layerOptions('maxCluster'));
        this.maxSquareLayer = L.rectangle([[0, 0], [0, 0]], layerOptions('maxSquare'));
        this.featureGroup = L.featureGroup([this.visitedTilesLayer, this.clusterTilesLayer, this.maxClusterTilesLayer]);

        this.statsNode?.querySelectorAll('[data-heatmap-tiles-swatch]').forEach(swatch => {
            const style = STYLES[swatch.getAttribute('data-heatmap-tiles-swatch')];
            if (!style) return;
            swatch.style.backgroundColor = false === style.fill ? 'transparent' : colorWithOpacity(color, style.fillOpacity);
            swatch.style.borderColor = colorWithOpacity(color, style.opacity);
        });
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
            .then(tiles => {
                this.tiles = tiles;
            })
            .catch(error => {
                this.loadPromise = null;
                throw error;
            });

        return this.loadPromise;
    }

    _sync() {
        if (!this.enabled || !this.tiles) {
            this.map.removeLayer(this.featureGroup);
            this.statsNode?.classList.add('hidden');
            return;
        }

        const {visited, cluster, maxCluster, maxSquare, stats} = this.tiles;
        this.visitedTilesLayer.setLatLngs(this._rings(visited));
        this.clusterTilesLayer.setLatLngs(this._rings(cluster));
        this.maxClusterTilesLayer.setLatLngs(this._rings(maxCluster));

        if (maxSquare) {
            this.maxSquareLayer.setBounds(L.latLngBounds(tileBounds(tileKey(maxSquare.x, maxSquare.y)))
                .extend(L.latLngBounds(tileBounds(tileKey(maxSquare.x + maxSquare.size - 1, maxSquare.y + maxSquare.size - 1)))));
            this.featureGroup.addLayer(this.maxSquareLayer);
        } else {
            this.featureGroup.removeLayer(this.maxSquareLayer);
        }
        this.featureGroup.addTo(this.map);

        if (this.statsNode) {
            this._setStat('total', stats.visitedTiles);
            this._setStat('clusterTiles', stats.clusterTiles);
            this._setStat('maxSquare', stats.maxSquare ? `${stats.maxSquare}×${stats.maxSquare}` : 0);
            this._setStat('maxCluster', stats.maxCluster);
            this.statsNode.classList.remove('hidden');
        }
    }

    _setStat(name, value) {
        const node = this.statsNode.querySelector(`[data-heatmap-tiles-stat="${name}"]`);
        if (node) node.innerText = value;
    }

    _rings(tiles) {
        return tiles.map(tile => {
            const [[south, west], [north, east]] = tileBounds(tile);
            return [[[north, west], [north, east], [south, east], [south, west]]];
        });
    }
}
