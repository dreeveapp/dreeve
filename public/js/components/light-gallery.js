import lightGallery from 'lightgallery';
import lgFullscreen from 'lightgallery/plugins/fullscreen'
import lgZoom from 'lightgallery/plugins/zoom'

const CONTAINER_SELECTOR = '[data-light-gallery]';
const ELEMENT_SELECTOR = '[data-light-gallery-element]';
const NAVIGATION_SELECTOR = 'a[data-model-content-url], a[data-router-content-url]';

const instances = new Map();

class LightGallery {
    constructor(container) {
        this.container = container;
        this.lastClicked = null;
        this.gallery = lightGallery(container, {
            dynamic: true,
            dynamicEl: [],
            plugins: [lgZoom, lgFullscreen],
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

        // The gallery can sit on top of a modal, without this the same Escape closes both.
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
        // Items are read from the DOM on every open so that whatever filtered them out stays respected.
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

export default function syncLightGalleries(rootNode) {
    for (const [container, instance] of instances) {
        if (container.isConnected) continue;

        instance.destroy();
        instances.delete(container);
    }

    rootNode.querySelectorAll(CONTAINER_SELECTOR).forEach(container => {
        if (instances.has(container)) return;

        instances.set(container, new LightGallery(container));
    });
}
