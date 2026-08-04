import L from 'leaflet';

/**
 * A polyline that keeps sub-pixel precision.
 *
 * Leaflet projects every vertex through map.latLngToLayerPoint(), which rounds the result to a
 * whole layer pixel. On a densely recorded GPS track that quantisation dominates the drawn shape:
 * a real bend of 1.7 degrees between two points turns into a 45 degree zigzag once both ends snap
 * to the pixel grid, and the track reads as a pixelated staircase. Raising smoothFactor hides it
 * only by throwing away so many vertices that the line becomes visibly straight chords instead.
 *
 * map.project() returns the same coordinate unrounded, so projecting through it lets the canvas
 * antialias the line and renders the shape that was actually recorded.
 *
 * Note: _projectLatlngs is Leaflet-internal (see Polyline in leaflet-src.js). Re-check this when
 * upgrading Leaflet.
 */
const SmoothPolyline = L.Polyline.extend({
    _projectLatlngs(latlngs, result, projectedBounds) {
        if (!(latlngs[0] instanceof L.LatLng)) {
            latlngs.forEach(ring => this._projectLatlngs(ring, result, projectedBounds));
            return;
        }

        const origin = this._map.getPixelOrigin();
        const zoom = this._map.getZoom();

        result.push(latlngs.map(latlng => {
            // project() hands back a fresh Point, so subtract in place rather than allocating a
            // second one per vertex. A 350km ride is ~50k vertices, re-projected on every zoom.
            const point = this._map.project(latlng, zoom)._subtract(origin);
            projectedBounds.extend(point);
            return point;
        }));
    },
});

export default function smoothPolyline(latlngs, options) {
    return new SmoothPolyline(latlngs, options);
}
