const CONTAINER_SELECTOR = '[data-light-gallery]';
const ELEMENT_SELECTOR = '[data-light-gallery-element]';
const NAVIGATION_SELECTOR = 'a[data-model-content-url], a[data-router-link]';

const instances = new Map();

let libPromise = null;
let lib = null;

const loadLib = () => libPromise ??= Promise.all([
    import(/* webpackChunkName: "lightgallery" */ 'lightgallery'),
    import(/* webpackChunkName: "lightgallery" */ 'lightgallery/plugins/zoom'),
    import(/* webpackChunkName: "lightgallery" */ 'lightgallery/plugins/fullscreen'),
]).then(([lightGallery, zoom, fullscreen]) => ({
    lightGallery: lightGallery.default,
    plugins: [zoom.default, fullscreen.default],
}));

class LightGallery {
    constructor(container) {
        this.container = container;
        this.lastClicked = null;
        this.gallery = lib.lightGallery(container, {
            dynamic: true,
            dynamicEl: [],
            plugins: lib.plugins,
            backdropDuration: 200,
            mobileSettings: {controls: false, showCloseIcon: true, download: false},
            ...JSON.parse(container.getAttribute('data-light-gallery') || '{}'),
        });

        this.onClick = e => {
            const element = e.target.closest(ELEMENT_SELECTOR);
            if (!element) return;

            e.preventDefault();
            this.lastClicked = element;
            this.open(element);
        };

        // Capture phase: the gallery has to be gone before the modal it links to opens underneath it.
        this.onNavigate = e => {
            if (!this.gallery.lgOpened) return;
            if (!e.target.closest('.lg-outer')) return;
            if (!e.target.closest(NAVIGATION_SELECTOR)) return;

            this.gallery.closeGallery();
        };

        this.onKeydown = e => {
            if ('Escape' !== e.key || !this.gallery.lgOpened) return;

            e.stopPropagation();
            this.gallery.closeGallery();
        };

        this.onAfterClose = () => this.lastClicked?.focus({preventScroll: true});

        this.container.addEventListener('click', this.onClick);
        this.container.addEventListener('lgAfterClose', this.onAfterClose);
        document.addEventListener('click', this.onNavigate, true);
        document.addEventListener('keydown', this.onKeydown, true);
    }

    open(element) {
        const elements = Array.from(this.container.querySelectorAll(ELEMENT_SELECTOR))
            .filter(node => node.getClientRects().length > 0);
        if (0 === elements.length) return;

        this.gallery.refresh(elements.map(
            node => JSON.parse(node.getAttribute('data-light-gallery-element'))
        ));
        this.gallery.openGallery(elements.indexOf(element));
    }

    destroy() {
        this.container.removeEventListener('click', this.onClick);
        this.container.removeEventListener('lgAfterClose', this.onAfterClose);
        document.removeEventListener('click', this.onNavigate, true);
        document.removeEventListener('keydown', this.onKeydown, true);
        this.gallery.destroy();
    }
}

export default async function initLightGalleries(rootNode) {
    for (const [container, instance] of instances) {
        if (container.isConnected) continue;

        instance.destroy();
        instances.delete(container);
    }

    const containers = Array.from(rootNode.querySelectorAll(CONTAINER_SELECTOR))
        .filter(container => !instances.has(container));
    if (0 === containers.length) return;

    lib = await loadLib();

    containers.forEach(container => instances.set(container, new LightGallery(container)));
}
