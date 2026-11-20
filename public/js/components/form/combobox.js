import {eventBus, Events} from '../../core/event-bus';
import {initComponents} from '../../core/component-registry';
import anchor from '../floating';

const ROOT_SELECTOR = '[data-combobox]';
const OPTION_SELECTOR = '[data-combobox-option]';

class Combobox {
    constructor(root) {
        this.root = root;
        this.input = root.querySelector('[data-combobox-input]');
        this.toggleButton = root.querySelector('[data-combobox-toggle]');
        this.panel = root.querySelector('[data-combobox-panel]');
        this.isRefocussing = false;
        this.cleanup = null;
        this.onDocumentClick = (event) => {
            if (!this.root.contains(event.target)) {
                this.close();
            }
        };
    }

    init() {
        // Rendered without options: a plain text input, nothing to wire up.
        if (!this.input || !this.toggleButton || !this.panel) {
            return;
        }

        this.panel.addEventListener('toggle', (event) => {
            if ('closed' !== event.newState) {
                return;
            }
            this.input.setAttribute('aria-expanded', 'false');
            this.detach();
        });

        this.toggleButton.addEventListener('click', () => {
            this.isOpen() ? this.close() : this.open();
            this.focusInput();
        });

        this.input.addEventListener('focus', () => {
            if (this.isRefocussing) {
                return;
            }
            this.open();
        });
        this.input.addEventListener('click', () => this.open());
        this.input.addEventListener('input', () => this.filterOptions());

        this.panel.addEventListener('click', (event) => {
            const option = event.target.closest(OPTION_SELECTOR);
            if (option) {
                this.select(option.getAttribute('data-combobox-option'));
            }
        });

        this.root.addEventListener('keydown', (event) => this.onKeydown(event));
        this.root.addEventListener('focusout', (event) => {
            if (!event.relatedTarget || this.root.contains(event.relatedTarget)) {
                return;
            }
            this.close();
        });
    }

    options() {
        return Array.from(this.panel.querySelectorAll(OPTION_SELECTOR));
    }

    visibleOptions() {
        return this.options().filter((option) => !option.parentElement.classList.contains('hidden'));
    }

    isOpen() {
        return this.panel.matches(':popover-open');
    }

    show() {
        if (!this.isOpen()) {
            this.panel.showPopover();
            this.cleanup = anchor({
                reference: this.root,
                floating: this.panel,
                placement: 'bottom-start',
                offset: 6,
                matchReferenceWidth: true,
            });
            document.addEventListener('click', this.onDocumentClick, true);
        }

        this.input.setAttribute('aria-expanded', 'true');
        this.options().forEach((option) => option.setAttribute(
            'aria-selected',
            String(option.getAttribute('data-combobox-option') === this.input.value)
        ));
    }

    onKeydown(event) {
        const isOpen = this.isOpen();

        if ('Escape' === event.key && isOpen) {
            // Don't let it bubble up to the surrounding form.
            event.preventDefault();
            this.close();
            this.focusInput();

            return;
        }

        if ('ArrowDown' === event.key || 'ArrowUp' === event.key) {
            event.preventDefault();
            if (!isOpen) {
                this.open();
            }
            this.moveFocus('ArrowDown' === event.key ? 1 : -1);

            return;
        }

        if ('Enter' === event.key && isOpen && event.target === this.input) {
            event.preventDefault();
            this.close();
        }
    }

    moveFocus(step) {
        const options = this.visibleOptions();
        if (0 === options.length) {
            return;
        }

        const current = options.indexOf(document.activeElement);
        const next = current < 0 ? (step > 0 ? 0 : options.length - 1) : current + step;

        options[(next + options.length) % options.length].focus();
    }

    select(value) {
        this.input.value = value;
        // The repeater seeds values silently, components like dependent-form-input listen for change.
        this.input.dispatchEvent(new Event('input', {bubbles: true}));
        this.input.dispatchEvent(new Event('change', {bubbles: true}));
        this.close();
        this.focusInput();
    }

    // Opening always shows the full list; the filter only narrows it down while typing.
    open() {
        this.options().forEach((option) => option.parentElement.classList.remove('hidden'));
        this.show();
    }

    filterOptions() {
        const term = this.input.value.trim().toLowerCase();
        this.options().forEach((option) => option.parentElement.classList.toggle(
            'hidden',
            '' !== term && !option.textContent.toLowerCase().includes(term)
        ));

        if (0 === this.visibleOptions().length) {
            this.close();

            return;
        }

        this.show();
    }

    close() {
        if (this.isOpen()) {
            this.panel.hidePopover();
        }
    }

    detach() {
        this.cleanup?.();
        this.cleanup = null;
        document.removeEventListener('click', this.onDocumentClick, true);
    }

    // Focusing the input opens the panel. Focus events are dispatched synchronously, so guarding
    // with a flag is enough to keep our own focus calls from reopening what we just closed.
    focusInput() {
        this.isRefocussing = true;
        this.input.focus();
        this.isRefocussing = false;
    }

    destroy() {
        this.detach();
    }
}

export default function initComboboxes(rootNode = document) {
    initComponents(rootNode, ROOT_SELECTOR, Combobox);
}

eventBus.on(Events.REPEATER_CHANGED, ({repeater}) => initComboboxes(repeater));
