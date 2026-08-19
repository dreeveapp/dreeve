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
