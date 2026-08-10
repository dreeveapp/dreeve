export const FilterName = Object.freeze({
    ACTIVITIES: 'activities',
    SEGMENTS: 'segments',
    PHOTO_WALL: 'photoWall',
    HEATMAP: 'heatmap',
});

export class FilterStorage {
    static storageKey = 'filters';

    static clearAll(tableName) {
        FilterStorage.set(tableName, {});
    }

    static get(tableName) {
        const storedJson = localStorage.getItem(FilterStorage.storageKey);
        if (!storedJson) return {};
        const parsed = JSON.parse(storedJson);
        return parsed[tableName] || {};
    }

    static set(tableName, object) {
        const storedJson = localStorage.getItem(FilterStorage.storageKey);
        const existing = storedJson ? JSON.parse(storedJson) : {};
        existing[tableName] = object;
        localStorage.setItem(FilterStorage.storageKey, JSON.stringify(existing));
    }
}

export class HiddenColumnStorage {
    static storageKey = 'hiddenColumns';

    static get(tableName) {
        const storedJson = localStorage.getItem(HiddenColumnStorage.storageKey);
        if (!storedJson) return [];
        const parsed = JSON.parse(storedJson);
        return parsed[tableName] || [];
    }

    static set(tableName, columnKeys) {
        const storedJson = localStorage.getItem(HiddenColumnStorage.storageKey);
        const existing = storedJson ? JSON.parse(storedJson) : {};
        existing[tableName] = columnKeys;
        localStorage.setItem(HiddenColumnStorage.storageKey, JSON.stringify(existing));
    }
}
