import {dispatchCommand} from "../utils";

class SortableList {
    constructor(list) {
        this.list = list;
        this.dragged = null;
        this.orderBeforeDrag = null;
    }

    init() {
        this.list.addEventListener('mousedown', (event) => {
            const handle = event.target.closest('[data-drag-handle]');
            if (handle) {
                handle.closest('[data-sort-item]')?.setAttribute('draggable', 'true');
            }
        });
        this.list.addEventListener('mouseup', (event) => {
            const handle = event.target.closest('[data-drag-handle]');
            if (handle) {
                // Clear draggable if the handle was pressed but no drag was started.
                handle.closest('[data-sort-item]')?.removeAttribute('draggable');
            }
        });

        this.list.addEventListener('dragstart', (event) => {
            this.dragged = event.target.closest('[data-sort-item]');
            if (!this.dragged) {
                return;
            }
            this.dragged.classList.add('opacity-40');
            event.dataTransfer.effectAllowed = 'move';
            this.orderBeforeDrag = this.currentOrder();
        });

        this.list.addEventListener('dragend', () => {
            if (!this.dragged) {
                return;
            }
            this.dragged.classList.remove('opacity-40');
            this.dragged.removeAttribute('draggable');
            this.dragged = null;

            const order = this.currentOrder();
            if (order.join(',') !== (this.orderBeforeDrag ?? []).join(',')) {
                this.onReorder(order);
            }
        });

        this.list.addEventListener('dragover', (event) => {
            event.preventDefault();
            if (!this.dragged) {
                return;
            }
            const item = event.target.closest('[data-sort-item]');
            if (!item || item === this.dragged) {
                return;
            }
            const box = item.getBoundingClientRect();
            const before = (event.clientY - box.top) < box.height / 2;
            if (before && item.previousElementSibling !== this.dragged) {
                this.list.insertBefore(this.dragged, item);
            } else if (!before && item.nextElementSibling !== this.dragged) {
                this.list.insertBefore(this.dragged, item.nextElementSibling);
            }
        });
    }

    currentOrder() {
        return Array.from(this.list.querySelectorAll('[data-sort-item]'))
            .map((item) => item.getAttribute('data-sort-id'))
            .filter((id) => null !== id && '' !== id);
    }

    onReorder(_order) {}
}

class PersistableSortableList extends SortableList {
    constructor(list) {
        super(list);
        this.command = list.getAttribute('data-save-order-command');
        this.status = list.querySelector('[data-sort-status]');
    }

    async onReorder(order) {
        this.setStatus('saving', 'Saving...');
        try {
            await dispatchCommand(this.command, {order});
            this.setStatus('success', 'Saved');
        } catch (error) {
            this.setStatus('error', error.message);
        }
    }

    setStatus(state, message) {
        if (!this.status) {
            return;
        }
        this.status.textContent = message;
        this.status.dataset.status = state;
    }
}

export default function initSortableLists(rootNode = document) {
    rootNode.querySelectorAll('[data-sortable-list]').forEach((list) => {
        const ListClass = list.hasAttribute('data-save-order-command') ? PersistableSortableList : SortableList;
        new ListClass(list).init();
    });
}
