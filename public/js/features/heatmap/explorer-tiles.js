export const ZOOM = 14;
export const TILES_PER_AXIS = 1 << ZOOM;

const tileToLongitude = (x) => x / TILES_PER_AXIS * 360 - 180;
const tileToLatitude = (y) => Math.atan(Math.sinh(Math.PI * (1 - 2 * y / TILES_PER_AXIS))) * 180 / Math.PI;

export const tileX = (key) => Math.floor(key / TILES_PER_AXIS);
export const tileY = (key) => key % TILES_PER_AXIS;

const neighbours = (key) => {
    const x = tileX(key);
    const y = tileY(key);

    return [
        x > 0 ? key - TILES_PER_AXIS : null,
        x < TILES_PER_AXIS - 1 ? key + TILES_PER_AXIS : null,
        y > 0 ? key - 1 : null,
        y < TILES_PER_AXIS - 1 ? key + 1 : null,
    ];
};

export const clusters = (visitedTiles) => {
    const clusterTiles = new Set(
        [...visitedTiles].filter(tile => neighbours(tile).every(neighbour => visitedTiles.has(neighbour)))
    );

    let maxCluster = new Set();
    const seen = new Set();
    clusterTiles.forEach(start => {
        if (seen.has(start)) return;

        const cluster = new Set([start]);
        const queue = [start];
        seen.add(start);
        while (queue.length > 0) {
            neighbours(queue.pop()).forEach(neighbour => {
                if (!clusterTiles.has(neighbour) || seen.has(neighbour)) return;
                seen.add(neighbour);
                cluster.add(neighbour);
                queue.push(neighbour);
            });
        }

        if (cluster.size > maxCluster.size) {
            maxCluster = cluster;
        }
    });

    return {clusterTiles, maxCluster};
};

export const maxSquare = (visitedTiles) => {
    const sizes = new Map();
    let max = null;
    [...visitedTiles].sort((a, b) => a - b).forEach(tile => {
        const x = tileX(tile);
        const y = tileY(tile);
        const size = 1 + Math.min(
            x > 0 ? sizes.get(tile - TILES_PER_AXIS) ?? 0 : 0,
            y > 0 ? sizes.get(tile - 1) ?? 0 : 0,
            x > 0 && y > 0 ? sizes.get(tile - TILES_PER_AXIS - 1) ?? 0 : 0,
        );
        sizes.set(tile, size);

        if (!max || size > max.size) {
            max = {x: x - size + 1, y: y - size + 1, size: size};
        }
    });

    return max;
};

export const tileKey = (x, y) => x * TILES_PER_AXIS + y;

export const tileBounds = (key) => {
    const x = tileX(key);
    const y = tileY(key);

    return [
        [tileToLatitude(y + 1), tileToLongitude(x)],
        [tileToLatitude(y), tileToLongitude(x + 1)],
    ];
};
