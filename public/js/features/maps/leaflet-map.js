import {fetchJson} from "../../utils";
import {loadEcharts} from "../charts/echarts-loader";
import L from 'leaflet';
import {createMapToolsControl} from "./leaflet-controls";
import './ctrl-scroll-zoom';
import {END_MARKER_COLOR, MARKER_BORDER_COLOR, PRIMARY_COLOR, START_MARKER_COLOR} from "./map-colors";

export default class LeafletMap {
    constructor(mapNode, data, config) {
        this.mapNode = mapNode;
        this.data = data;
        this.config = config;

        this.isInteractive = false !== data.interactive;
        this.map = L.map(mapNode, {
            ctrlScrollZoom: this.isInteractive && config.requireCtrlToZoom,
            minZoom: data.minZoom,
            maxZoom: data.maxZoom,
            zoomSnap: .5,
            zoomDelta: .5,
            preferCanvas: true,
            zoomControl: this.isInteractive,
            dragging: this.isInteractive,
            scrollWheelZoom: this.isInteractive,
            doubleClickZoom: this.isInteractive,
            touchZoom: this.isInteractive,
            boxZoom: this.isInteractive,
            keyboard: this.isInteractive,
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
                smoothFactor: 1.0
            }).addTo(featureGroup);

            if (this.data.showStartMarker) {
                this.addCircleMarker(coordinates[0], START_MARKER_COLOR).addTo(featureGroup);
            }
            if (this.data.showEndMarker) {
                this.addCircleMarker(coordinates.at(-1), END_MARKER_COLOR).addTo(featureGroup);
            }
        }

        if (this.data.imageOverlay) {
            L.imageOverlay(this.data.imageOverlay, this.data.bounds, {
                attribution: '© <a href="https://zwift.com" rel="noreferrer noopener">Zwift</a>',
            }).addTo(this.map);
            this.map.setMaxBounds(this.data.bounds);
        }

        featureGroup.addTo(this.map);
        const imageMarkers = this.data.imageMarkers || [];
        const imageMarkersLayer = L.layerGroup(imageMarkers.map(imageMarker => this.addImageMarker(imageMarker))).addTo(this.map);
        this.map.fitBounds(featureGroup.getBounds(), {maxZoom: this.data.maxZoom});
        if (this.isInteractive) {
            this.map.addControl(createMapToolsControl({
                bounds: featureGroup.getBounds(),
                toggles: imageMarkers.length > 0 ? [{
                    icon: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="14" height="14" fill="none"><path stroke="currentColor" stroke-linejoin="round" stroke-width="2" d="M4 18V8a1 1 0 0 1 1-1h1.5l1.707-1.707A1 1 0 0 1 8.914 5h6.172a1 1 0 0 1 .707.293L17.5 7H19a1 1 0 0 1 1 1v10a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1Z"/><path stroke="currentColor" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/></svg>',
                    title: this.data.imageMarkersTitle,
                    active: true,
                    onToggle: (active) => active ? imageMarkersLayer.addTo(this.map) : imageMarkersLayer.remove(),
                }] : [],
            }));
        }
    }

    async connectToEChart() {
        if (!this.mapNode.hasAttribute('data-leaflet-echart-connect')) {
            return;
        }

        const eChartNode = document.querySelector('div[data-echarts-options-url][data-leaflet-echart-connect]');
        if (!eChartNode) {
            return;
        }

        const coordinatesUrl = eChartNode.getAttribute('data-leaflet-echart-connect');
        if (!coordinatesUrl) {
            return;
        }

        try {
            const coordinateMap = await fetchJson(coordinatesUrl);
            await loadEcharts();
            const marker = this.addCircleMarker([0, 0], PRIMARY_COLOR, {radius: 6, opacity: 0}).addTo(this.map);
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
            color: MARKER_BORDER_COLOR,
            fillColor,
            fillOpacity: opacity,
            opacity,
        });
    }

    addImageMarker({latLng, imageUrl, lightGalleryTarget}) {
        const link = document.createElement('a');
        link.href = '#';
        link.className = 'leaflet-image-marker';
        link.setAttribute('data-light-gallery-target', lightGalleryTarget);

        const image = document.createElement('img');
        image.src = imageUrl;
        image.alt = '';
        image.loading = 'lazy';
        link.appendChild(image);

        return L.marker(latLng, {
            keyboard: false,
            icon: L.divIcon({
                html: link,
                className: '',
                iconSize: [40, 40],
            }),
        });
    }

}
