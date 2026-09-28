export default function initLeafletMaps(rootNode) {
    rootNode.querySelectorAll('[data-leaflet]').forEach(async mapNode => {
        const {default: LeafletMap} = await import(
            /* webpackChunkName: "leaflet" */ './leaflet-map'
            );

        const data = JSON.parse(mapNode.getAttribute('data-leaflet'));
        const config = window.dreeve.leafletConfig;
        if (config.enableGreyScale) {
            mapNode.classList.add('enable-grey-scale');
        }
        const leafletMap = new LeafletMap(mapNode, data, config);

        await leafletMap.addRoutes();
        leafletMap.connectToEChart();
    });
}
