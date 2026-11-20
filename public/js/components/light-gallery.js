const CONTAINER_SELECTOR = '[data-light-gallery]';
const ELEMENT_SELECTOR = '[data-light-gallery-element]';
const TARGET_SELECTOR = '[data-light-gallery-target]';

const galleries = new WeakMap();

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

        this.onKeydown = e => {
            if ('Escape' !== e.key || !this.gallery.lgOpened) return;

            e.stopPropagation();
            this.gallery.closeGallery();
        };

        this.onAfterClose = () => this.lastClicked?.focus({preventScroll: true});

        this.container.addEventListener('lgAfterClose', this.onAfterClose);
        document.addEventListener('keydown', this.onKeydown, true);
        galleries.set(container, this);
    }

    open(element) {
        const elements = Array.from(this.container.querySelectorAll(ELEMENT_SELECTOR))
            .filter(node => !node.classList.contains('hidden'));
        const index = elements.indexOf(element);
        if (-1 === index) return;

        this.gallery.refresh(elements.map(
            node => JSON.parse(node.getAttribute('data-light-gallery-element'))
        ));
        this.gallery.openGallery(index);
    }
}

document.addEventListener('click', e => {
    const trigger = e.target.closest(`${TARGET_SELECTOR}, ${ELEMENT_SELECTOR}`);
    if (!trigger) return;

    const element = trigger.matches(TARGET_SELECTOR)
        ? document.getElementById(trigger.getAttribute('data-light-gallery-target'))
        : trigger;
    if (!element?.matches(ELEMENT_SELECTOR)) return;

    const gallery = galleries.get(element.closest(CONTAINER_SELECTOR));
    if (!gallery) return;

    e.preventDefault();
    gallery.lastClicked = trigger;
    gallery.open(element);
});

export default async function initLightGalleries(rootNode) {
    const containers = rootNode.querySelectorAll(CONTAINER_SELECTOR);
    if (0 === containers.length) return;

    lib = await loadLib();

    containers.forEach(container => new LightGallery(container));
}
