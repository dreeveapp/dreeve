const instances = new Map();

const destroyDetachedMaps = () => {
    for (const [mapNode, instance] of instances) {
        if (mapNode.isConnected) continue;

        instance.destroy();
        instances.delete(mapNode);
    }
};

export default function initLeafletMaps(rootNode) {
    destroyDetachedMaps();

    rootNode.querySelectorAll('[data-leaflet]').forEach(async mapNode => {
        if (instances.has(mapNode)) return;

        const {default: LeafletMap} = await import(
            /* webpackChunkName: "leaflet" */ './leaflet-map'
            );
        if (instances.has(mapNode)) return;

        const data = JSON.parse(mapNode.getAttribute('data-leaflet'));
        const config = window.dreeve.leafletConfig;
        if (config.enableGreyScale) {
            mapNode.classList.add('enable-grey-scale');
        }
        const leafletMap = new LeafletMap(mapNode, data, config);
        instances.set(mapNode, leafletMap);

        await leafletMap.addRoutes();
        leafletMap.connectToEChart();
    });
}
