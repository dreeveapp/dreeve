import {eventBus, Events} from "../core/event-bus";
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
            const modalId = node.getAttribute('data-model-content-url');
            this.open(modalId);
            this.router.pushCurrentRouteToHistoryState(modalId);
        });
    }

    open(modalId) {
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

                // Re-register nav items that may have been added dynamically
                const newNavItems = this.modalSkeletonNode.querySelectorAll('a[data-router-content-url]');
                this.router.registerNavItems(newNavItems);
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