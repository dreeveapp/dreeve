export const ZOOM = 14;
export const TILES_PER_AXIS = 1 << ZOOM;

const tileToLongitude = (x) => x / TILES_PER_AXIS * 360 - 180;
const tileToLatitude = (y) => Math.atan(Math.sinh(Math.PI * (1 - 2 * y / TILES_PER_AXIS))) * 180 / Math.PI;

export const tileX = (key) => Math.floor(key / TILES_PER_AXIS);
export const tileY = (key) => key % TILES_PER_AXIS;

export const tileBounds = (key) => {
    const x = tileX(key);
    const y = tileY(key);

    return [
        [tileToLatitude(y + 1), tileToLongitude(x)],
        [tileToLatitude(y), tileToLongitude(x + 1)],
    ];
};
