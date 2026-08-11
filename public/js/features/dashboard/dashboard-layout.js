import {debounce, dispatchCommand} from "../../utils";
import createDragSort from "../../components/drag-sort";

const WIDTHS = new Set([33, 50, 66, 100]);

class DashboardLayout {
    constructor(form) {
        this.form = form;
        this.grid = form.querySelector('[data-widget-grid]');
        this.field = form.querySelector('[data-layout-field]');
        this.status = form.querySelector('[data-layout-status]');
        this.saveCommand = form.getAttribute('data-save-command');
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
        createDragSort(this.grid, {
            itemSelector: '[data-widget-id]',
            invertSwap: true,
            invertedSwapThreshold: 0.65,
            onReorder: () => {
                this.syncField();
                this.save();
            },
        }).catch((error) => console.error('Failed to initialise dashboard layout drag and drop:', error));
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
