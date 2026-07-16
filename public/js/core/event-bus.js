export const Events = Object.freeze({
    PAGE_LOADED:                'page:loaded',
    MODAL_LOADED:               'modal:loaded',
    MODAL_HISTORY_CHANGED:      'modal:historyChanged',
    DARK_MODE_TOGGLED:          'darkMode:toggled',
    FULLSCREEN_ENABLED:         'fullscreen:enabled',
    TAB_CHANGED:                'tab:changed',
    SIDEBAR_RESIZED:            'sidebar:resized',
    NAVIGATION_CLICKED:         'navigation:clicked',
    DATA_TABLE_CLUSTER_CHANGED: 'dataTable:clusterChanged',
    REPEATER_CHANGED:           'repeater:changed',
});

class EventBus {
    constructor() {
        this._listeners = {};
    }

    on(event, handler) {
        if (!this._listeners[event]) {
            this._listeners[event] = [];
        }
        this._listeners[event].push(handler);
        return this;
    }

    off(event, handler) {
        if (!this._listeners[event]) return this;
        this._listeners[event] = this._listeners[event].filter(h => h !== handler);
        return this;
    }

    emit(event, detail = {}) {
        if (!this._listeners[event]) return this;
        for (const handler of this._listeners[event]) {
            handler(detail);
        }
        return this;
    }

    async emitAsync(event, detail = {}) {
        if (!this._listeners[event]) return this;
        for (const handler of this._listeners[event]) {
            await handler(detail);
        }
        return this;
    }
}

export const eventBus = new EventBus();
