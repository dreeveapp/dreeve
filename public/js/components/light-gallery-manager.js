const CONTAINER_SELECTOR = '[data-light-gallery]';

let galleryModule = null;

export default function initLightGalleries(rootNode) {
    // Once the module is loaded it has to keep running, it also reclaims galleries whose container got removed.
    if (!galleryModule && !rootNode.querySelector(CONTAINER_SELECTOR)) {
        return;
    }

    galleryModule ??= import(
        /* webpackChunkName: "lightgallery" */ './light-gallery'
        );
    galleryModule.then(({default: syncLightGalleries}) => syncLightGalleries(rootNode));
}
