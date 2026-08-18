import {eventBus, Events} from "../core/event-bus";
import {basePath} from "../utils";
import { Modal } from 'flowbite';

export default class ModalManager {
    constructor(router) {
        this.router = router;
        this.modalSkeletonNode = document.getElementById('modal-skeleton');
        this.modalContent = this.modalSkeletonNode.querySelector('#modal-content');
        this.modalSpinner = this.modalSkeletonNode.querySelector('.spinner');
        this.modal = null;
    }

    // Must be called exactly once at boot. The handler relies on closest(), so
    // a single delegated listener on document covers page content, (re)loaded
    // modal content and dynamically added rows.
    init() {
        document.addEventListener('click', (e) => {
            const node = e.target.closest('a[data-model-content-url]');
            if (!node) return;

            e.preventDefault();
            e.stopPropagation();
            this.openAndPushToHistoryState(node.getAttribute('data-model-content-url'));
        });
    }

    openAndPushToHistoryState(modalId) {
        this.open(modalId);
        this.router.pushCurrentRouteToHistoryState(modalId);
    }

    // The modal URL reaches us from the address bar, so it is attacker-controllable:
    // without this, a crafted link makes us innerHTML a remote document.
    assertSameOriginPath(modalId) {
        if (!modalId?.trim()) {
            throw new Error('Modal url is empty');
        }

        const url = new URL(modalId, window.location.origin);
        const prefix = `${basePath()}/`;

        if (url.origin !== window.location.origin
            || !url.pathname.startsWith(prefix)
            || url.pathname.length <= prefix.length) {
            throw new Error(`Modal url "${modalId}" is not an url within this app`);
        }
    }

    open(modalId) {
        this.assertSameOriginPath(modalId);

        this.close();

        // Show loading state.
        this.modalSpinner.classList.remove('hidden');
        this.modalSpinner.classList.add('flex');

        this.modal = new Modal(this.modalSkeletonNode, {
            placement: 'bottom',
            closable: true,
            backdropClasses: 'bg-gray-900/50 fixed inset-0 z-1400',
            onShow: async () => {
                const response = await fetch(modalId, {cache: 'no-store'});
                // Remove loading state.
                this.modalSpinner.classList.add('hidden');
                this.modalSpinner.classList.remove('flex');

                this.modalContent.innerHTML = await response.text();
                const modalName = modalId.replace(/^\/+/, '').replaceAll('/', '-');
                eventBus.emit(Events.MODAL_LOADED, {node: this.modalSkeletonNode, modalName});
                // Modal close event listeners.
                const closeButton = this.modalContent.querySelector('button.close');
                if (closeButton) {
                    this.modalContent.querySelector('button.close').addEventListener('click', (e) => {
                        e.preventDefault();
                        e.stopPropagation();
                        this.modal.hide();
                        this.router.pushCurrentRouteToHistoryState();
                    });
                }

                document.body.addEventListener('keydown', (e) => {
                    if (e.key !== 'Escape') {
                        return;
                    }
                    this.router.pushCurrentRouteToHistoryState();
                }, {once: true});

                document.body.addEventListener('click', (e) => {
                    if (e.target.id !== 'modal-skeleton') {
                        return;
                    }
                    this.router.pushCurrentRouteToHistoryState();
                }, {once: true});
            },
            onHide: () => {
                this.modalContent.innerHTML = '';
            }
        });

        this.modal.show();
    }

    close() {
        if (!this.modal) {
            return;
        }

        this.modal.hide();
    }
}