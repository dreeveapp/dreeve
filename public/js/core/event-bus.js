export const Events = Object.freeze({
    PAGE_LOADED:                'page:loaded',
    ASYNC_CONTENT_LOADED:       'asyncContent:loaded',
    DARK_MODE_TOGGLED:          'darkMode:toggled',
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
}

export const eventBus = new EventBus();
