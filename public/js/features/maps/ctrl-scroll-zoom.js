import L from 'leaflet';

const WARNING_CLASS = 'leaflet-ctrl-scroll-zoom-warning';
const MESSAGE_ATTR = 'data-ctrl-scroll-zoom-message';
const WARNING_HIDE_DELAY = 1000;

const isMac = typeof navigator !== 'undefined' && navigator.platform.toUpperCase().indexOf('MAC') >= 0;
const defaultMessage = isMac
    ? 'Use ⌘ + scroll to zoom the map'
    : 'Use ctrl + scroll to zoom the map';

const CtrlScrollZoom = L.Handler.extend({
    addHooks: function() {
        this._onWheel = this._onWheel.bind(this);

        const message = this._map.options.ctrlScrollZoomMessage || defaultMessage;
        this._map._container.setAttribute(MESSAGE_ATTR, message);

        this._map._container.addEventListener('wheel', this._onWheel, {capture: true, passive: false});
    },

    removeHooks: function() {
        this._map._container.removeEventListener('wheel', this._onWheel, {capture: true});
        this._map._container.removeAttribute(MESSAGE_ATTR);
        L.DomUtil.removeClass(this._map._container, WARNING_CLASS);
        clearTimeout(this._hideTimeout);
    },

    _onWheel: function(event) {
        if (event.ctrlKey || event.metaKey) {
            L.DomUtil.removeClass(this._map._container, WARNING_CLASS);
            clearTimeout(this._hideTimeout);
            return;
        }
        event.stopImmediatePropagation();
        L.DomUtil.addClass(this._map._container, WARNING_CLASS);

        clearTimeout(this._hideTimeout);
        this._hideTimeout = setTimeout(() => {
            L.DomUtil.removeClass(this._map._container, WARNING_CLASS);
        }, WARNING_HIDE_DELAY);
    }
});

L.Map.addInitHook('addHandler', 'ctrlScrollZoom', CtrlScrollZoom);
