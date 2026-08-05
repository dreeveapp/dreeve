import {eventBus, Events} from '../../core/event-bus';
import {getRepeaterInstance} from '../../components/form/dispatch-command-form';

const ROOT_SELECTOR = '[data-basemap-preview]';
const MAP_SELECTOR = '[data-basemap-preview-map]';
const CUSTOM = 'custom';
const SAMPLE_CENTER = [50.9, 5.35];
const SAMPLE_ZOOM = 12;
// A short arbitrary route, purely so the polyline color is visible.
const SAMPLE_ROUTE = [
    [50.912, 5.332], [50.909, 5.341], [50.903, 5.347], [50.898, 5.358],
    [50.894, 5.366], [50.897, 5.375], [50.903, 5.379],
];

const instances = new Map();

class BasemapPreview {
    constructor(root) {
        this.root = root;
        this.mapNode = root.querySelector(MAP_SELECTOR);
        this.presetSelect = root.querySelector('[data-basemap-preset]');
        this.customUrlsWrapper = root.querySelector('[data-basemap-custom-urls]');
        this.greyScaleToggle = root.querySelector('[data-basemap-greyscale]');
        this.colorPicker = root.querySelector('[data-basemap-color-picker]');
        this.colorText = root.querySelector('[data-basemap-color-text]');

        const options = JSON.parse(root.getAttribute('data-basemap-preview') || '{}');
        this.presets = options.presets || {};

        this.L = null;
        this.map = null;
        this.tileLayers = [];
        this.polyline = null;
        this.isMounting = false;
        this.isDestroyed = false;
    }

    init() {
        if (!this.mapNode || !this.presetSelect) {
            return;
        }

        this.presetSelect.addEventListener('change', () => {
            this.applyPreset();
            this.render();
        });

        // Repeater rows are created after init, so listen on the wrapper.
        this.customUrlsWrapper?.addEventListener('input', () => this.render());
        eventBus.on(Events.REPEATER_CHANGED, ({repeater}) => {
            if (this.customUrlsWrapper?.contains(repeater)) {
                this.render();
            }
        });

        this.greyScaleToggle?.addEventListener('change', () => this.render());

        // Keep the color swatch and the free-text field in sync. The text field is the
        // one that submits, because CssColor accepts more than the picker can express.
        this.colorPicker?.addEventListener('input', () => {
            this.colorText.value = this.colorPicker.value;
            this.render();
        });
        this.colorText?.addEventListener('input', () => {
            if (/^#([0-9a-f]{6})$/i.test(this.colorText.value)) {
                this.colorPicker.value = this.colorText.value;
            }
            this.render();
        });

        this.resizeObserver = new ResizeObserver(() => this.handleResize());
        this.resizeObserver.observe(this.mapNode);

        this.toggleCustomUrls();
    }

    toggleCustomUrls() {
        // Hide with a class, never data-dependent-on: hidden inputs must still submit.
        this.customUrlsWrapper?.classList.toggle('hidden', CUSTOM !== this.presetSelect.value);
    }

    urlInputs() {
        return Array.from(this.root.querySelectorAll('[data-basemap-url]'));
    }

    applyPreset() {
        this.toggleCustomUrls();

        const urls = this.presets[this.presetSelect.value];
        if (!urls) {
            return; // "custom" — leave whatever the user typed
        }

        const repeaterRoot = this.customUrlsWrapper?.querySelector('[data-repeater]');
        const repeater = repeaterRoot ? getRepeaterInstance(repeaterRoot) : null;

        // Reuse the existing rows, then add or drop rows to match the preset length.
        while (repeater && repeater.rows().length < urls.length) {
            repeater.addRow(null);
        }
        while (repeater && repeater.rows().length > urls.length) {
            repeater.removeRow(repeater.rows().at(-1));
        }

        this.urlInputs().forEach((input, index) => {
            input.value = urls[index] ?? '';
        });
    }

    tileLayerUrls() {
        return this.urlInputs()
            .map((input) => input.value.trim())
            .filter((url) => url.includes('{z}') && url.includes('{x}') && url.includes('{y}'));
    }

    polylineColor() {
        return this.colorText?.value.trim() || '#fc6719';
    }

    hasSize() {
        return this.mapNode.offsetWidth > 0 && this.mapNode.offsetHeight > 0;
    }

    handleResize() {
        if (!this.hasSize()) {
            return;
        }
        if (!this.map) {
            void this.mount();

            return;
        }
        this.map.invalidateSize();
    }

    async mount() {
        if (this.isMounting || this.map) {
            return;
        }
        this.isMounting = true;

        const {default: L} = await import(/* webpackChunkName: "leaflet" */ 'leaflet');

        this.isMounting = false;
        if (this.isDestroyed) {
            return;
        }

        this.L = L;
        this.map = L.map(this.mapNode, {
            zoomControl: false,
            attributionControl: false,
            scrollWheelZoom: false,
            dragging: false,
            doubleClickZoom: false,
            keyboard: false,
        });
        this.map.setView(SAMPLE_CENTER, SAMPLE_ZOOM);

        this.render();
    }

    render() {
        if (!this.map) {
            return;
        }

        this.tileLayers.forEach((layer) => layer.remove());
        this.tileLayers = this.tileLayerUrls().map((url) => this.L.tileLayer(url).addTo(this.map));

        this.mapNode.classList.toggle('enable-grey-scale', Boolean(this.greyScaleToggle?.checked));

        this.polyline?.remove();
        this.polyline = this.L.polyline(SAMPLE_ROUTE, {
            color: this.polylineColor(),
            weight: 3,
            opacity: 0.9,
            lineJoin: 'round',
        }).addTo(this.map);
    }

    destroy() {
        this.isDestroyed = true;
        this.resizeObserver?.disconnect();
        this.map?.remove();
        this.map = null;
    }
}

const destroyDetachedPreviews = () => {
    instances.forEach((preview, root) => {
        if (document.contains(root)) {
            return;
        }
        preview.destroy();
        instances.delete(root);
    });
};

export default function initBasemapPreviews(rootNode = document) {
    destroyDetachedPreviews();

    rootNode.querySelectorAll(ROOT_SELECTOR).forEach((root) => {
        if (instances.has(root)) {
            return;
        }

        const preview = new BasemapPreview(root);
        instances.set(root, preview);
        preview.init();
    });
}
