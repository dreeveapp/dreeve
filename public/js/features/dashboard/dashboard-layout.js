import {debounce, dispatchCommand} from "../../utils";

const WIDTHS = new Set([33, 50, 66, 100]);

class DashboardLayout {
    constructor(form) {
        this.form = form;
        this.grid = form.querySelector('[data-widget-grid]');
        this.field = form.querySelector('[data-layout-field]');
        this.status = form.querySelector('[data-layout-status]');
        this.saveCommand = form.getAttribute('data-save-command');
        this.dragged = null;
        this.saveSeq = 0;
        this.save = debounce(() => this.persist(), 400);
    }

    init() {
        if (!this.grid || !this.field || !this.saveCommand) {
            return;
        }

        this.wireDragAndDrop();
        this.wireWidthControls();
        this.syncField();
    }

    wireDragAndDrop() {
        this.grid.addEventListener('mousedown', (event) => {
            const handle = event.target.closest('[data-drag-handle]');
            if (handle) {
                handle.closest('[data-widget-id]')?.setAttribute('draggable', 'true');
            }
        });
        this.grid.addEventListener('mouseup', (event) => {
            const handle = event.target.closest('[data-drag-handle]');
            if (handle) {
                // Clear draggable if the handle was pressed but no drag was started.
                handle.closest('[data-widget-id]')?.removeAttribute('draggable');
            }
        });

        this.grid.addEventListener('dragstart', (event) => {
            this.dragged = event.target.closest('[data-widget-id]');
            if (!this.dragged) {
                return;
            }
            this.dragged.classList.add('opacity-40');
            event.dataTransfer.effectAllowed = 'move';
        });

        this.grid.addEventListener('dragend', () => {
            if (!this.dragged) {
                return;
            }
            this.dragged.classList.remove('opacity-40');
            this.dragged.removeAttribute('draggable');
            this.dragged = null;
            this.syncField();
            this.save();
        });

        this.grid.addEventListener('dragover', (event) => {
            event.preventDefault();
            if (!this.dragged) {
                return;
            }
            const card = event.target.closest('[data-widget-id]');
            if (!card || card === this.dragged) {
                return;
            }
            const box = card.getBoundingClientRect();
            const before = (event.clientX - box.left) < box.width / 2;
            if (before && card.previousElementSibling !== this.dragged) {
                this.grid.insertBefore(this.dragged, card);
            } else if (!before && card.nextElementSibling !== this.dragged) {
                this.grid.insertBefore(this.dragged, card.nextElementSibling);
            }
        });
    }

    wireWidthControls() {
        this.grid.addEventListener('click', (event) => {
            const button = event.target.closest('[data-width-seg] button[data-w]');
            if (!button) {
                return;
            }
            const card = button.closest('[data-widget-id]');
            const width = Number(button.dataset.w);
            if (!card || !WIDTHS.has(width)) {
                return;
            }

            this.applyWidth(card, width);
            this.syncField();
            this.save();
        });
    }

    applyWidth(card, width) {
        card.dataset.width = String(width);
        card.querySelectorAll('[data-width-seg] button[data-w]').forEach((button) => {
            button.setAttribute('aria-pressed', String(Number(button.dataset.w) === width));
        });
    }

    syncField() {
        const items = [...this.grid.querySelectorAll('[data-widget-id]')].map((card) => ({
            id: card.dataset.widgetId,
            width: Number(card.dataset.width),
        }));
        this.field.value = JSON.stringify(items);
    }

    async persist() {
        const seq = ++this.saveSeq;
        this.setStatus('saving', 'Saving...');

        try {
            const layout = JSON.parse(this.field.value || '[]');
            await dispatchCommand(this.saveCommand, {layout});
            if (seq === this.saveSeq) {
                this.setStatus('success', 'Saved');
            }
        } catch (error) {
            if (seq === this.saveSeq) {
                this.setStatus('error', error.message);
            }
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

export default function initDashboardLayout(rootNode = document) {
    rootNode.querySelectorAll('[data-dashboard-layout-form]').forEach((form) => new DashboardLayout(form).init());
}
