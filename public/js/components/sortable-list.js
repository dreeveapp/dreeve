import {dispatchCommand} from "../utils";
import createDragSort from "./drag-sort";

class SortableList {
    constructor(list) {
        this.list = list;
    }

    init() {
        createDragSort(this.list, {
            itemSelector: '[data-sort-item]',
            onReorder: (items) => this.onReorder(
                items
                    .map((item) => item.getAttribute('data-sort-id'))
                    .filter((id) => null !== id && '' !== id)
            ),
        }).catch((error) => console.error('Failed to initialise sortable list:', error));
    }

    onReorder(_order) {}
}

class PersistableSortableList extends SortableList {
    constructor(list) {
        super(list);
        this.command = list.getAttribute('data-save-order-command');
        this.status = list.querySelector('[data-sort-status]')
            ?? list.parentElement?.querySelector('[data-sort-status]');
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
