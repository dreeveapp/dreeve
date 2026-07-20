import {fetchJson} from "../../utils";
import L from 'leaflet';
import {createMapToolsControl} from "./leaflet-controls";
import './ctrl-scroll-zoom';

export default class LeafletMap {
    constructor(mapNode, data, config) {
        this.mapNode = mapNode;
        this.data = data;
        this.config = config;

        this.map = L.map(mapNode, {
            ctrlScrollZoom: true,
            minZoom: data.minZoom,
            maxZoom: data.maxZoom,
            zoomSnap: .5,
            zoomDelta: .5,
            preferCanvas: true,
        });

        if (data.tileLayer) {
            this.config.tileLayerUrls.forEach((tileLayerUrl) => {
                L.tileLayer(tileLayerUrl).addTo(this.map);
            });
        }
    }

    async addRoutes() {
        const featureGroup = L.featureGroup();
        const polylines = await fetchJson(this.data.polylineUrl);

        for (const coordinates of polylines) {
            L.polyline(coordinates, {
                color: this.config.polylineColor,
                weight: 2,
                opacity: 0.9,
                lineJoin: 'round',
                smoothFactor: 2.0
            }).addTo(featureGroup);

            if (this.data.showStartMarker) {
                this.addCircleMarker(coordinates[0], '#3ba272').addTo(featureGroup);
            }
            if (this.data.showEndMarker) {
                this.addCircleMarker(coordinates.at(-1), '#BD2D22').addTo(featureGroup);
            }
        }

        if (this.data.imageOverlay) {
            L.imageOverlay(this.data.imageOverlay, this.data.bounds, {
                attribution: '© <a href="https://zwift.com" rel="noreferrer noopener">Zwift</a>',
            }).addTo(this.map);
            this.map.setMaxBounds(this.data.bounds);
        }

        featureGroup.addTo(this.map);
        this.map.fitBounds(featureGroup.getBounds(), {maxZoom: this.data.maxZoom});
        this.map.addControl(createMapToolsControl({bounds: featureGroup.getBounds()}));
    }

    async connectToEChart() {
        if (!this.mapNode.hasAttribute('data-leaflet-echart-connect')) {
            return;
        }

        const eChartNode = document.querySelector('div[data-echarts-options][data-leaflet-echart-connect]');
        if (!eChartNode) {
            return;
        }

        const coordinatesUrl = eChartNode.getAttribute('data-leaflet-echart-connect');
        if (!coordinatesUrl) {
            return;
        }

        try {
            const coordinateMap = await fetchJson(coordinatesUrl);
            const marker = this.addCircleMarker([0, 0], '#F26722', {radius: 6, opacity: 0}).addTo(this.map);
            const chart = echarts.getInstanceByDom(eChartNode);
            const initialZoom = this.map.getZoom();

            chart.on('updateAxisPointer', (event) => {
                const coordinate = event.dataIndex === undefined ? undefined : coordinateMap[event.dataIndex];
                if (!coordinate) {
                    marker.setStyle({opacity: 0, fillOpacity: 0});
                    return;
                }

                marker.setLatLng(coordinate);
                marker.setStyle({opacity: 1, fillOpacity: 1});

                const shouldPan = this.map.getZoom() > initialZoom || !this.map.getBounds().contains(coordinate);
                if (shouldPan) {
                    this.map.panTo(coordinate);
                }
            });
        } catch (error) {
            console.error('Failed to load coordinate map:', error);
        }
    }

    addCircleMarker(latLng, fillColor, {radius = 8, opacity = 1} = {}) {
        return L.circleMarker(latLng, {
            radius,
            color: '#303030',
            fillColor,
            fillOpacity: opacity,
            opacity,
        });
    }

}
