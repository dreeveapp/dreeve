import {Dropdown} from 'flowbite';
import {eventBus, Events} from '../../core/event-bus';

const ROOT_SELECTOR = '[data-combobox]';
const OPTION_SELECTOR = '[data-combobox-option]';

const instances = new Map();
let instanceCount = 0;

class Combobox {
    constructor(root) {
        this.root = root;
        this.input = root.querySelector('[data-combobox-input]');
        this.toggleButton = root.querySelector('[data-combobox-toggle]');
        this.panel = root.querySelector('[data-combobox-panel]');
        this.instanceId = `combobox-${++instanceCount}`;
        this.isRefocussing = false;
    }

    init() {
        // Rendered without options: a plain text input, nothing to wire up.
        if (!this.input || !this.toggleButton || !this.panel) {
            return;
        }

        this.dropdown = new Dropdown(this.panel, this.root, {
            placement: 'bottom-start',
            offsetDistance: 6,
            triggerType: 'none',
            onShow: () => this.onShow(),
            onHide: () => this.input.setAttribute('aria-expanded', 'false'),
        }, {id: this.instanceId, override: true});

        this.toggleButton.addEventListener('click', () => {
            this.dropdown.isVisible() ? this.close() : this.open();
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

    onShow() {
        this.input.setAttribute('aria-expanded', 'true');
        this.options().forEach((option) => option.setAttribute(
            'aria-selected',
            String(option.getAttribute('data-combobox-option') === this.input.value)
        ));
    }

    onKeydown(event) {
        const isOpen = this.dropdown.isVisible();

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
        this.dropdown.show();
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

        this.dropdown.show();
    }

    close() {
        if (this.dropdown?.isVisible()) {
            this.dropdown.hide();
        }
    }

    // Focusing the input opens the panel. Focus events are dispatched synchronously, so guarding
    // with a flag is enough to keep our own focus calls from reopening what we just closed.
    focusInput() {
        this.isRefocussing = true;
        this.input.focus();
        this.isRefocussing = false;
    }

    destroy() {
        // Hide first, flowbite only detaches its document wide click outside listener there.
        this.close();
        this.dropdown?.destroyAndRemoveInstance();
    }
}

const destroyDetachedComboboxes = () => {
    instances.forEach((combobox, root) => {
        if (document.contains(root)) {
            return;
        }
        combobox.destroy();
        instances.delete(root);
    });
};

export default function initComboboxes(rootNode = document) {
    destroyDetachedComboboxes();

    rootNode.querySelectorAll(ROOT_SELECTOR).forEach((root) => {
        if (instances.has(root)) {
            return;
        }
        const combobox = new Combobox(root);
        instances.set(root, combobox);
        combobox.init();
    });
}

eventBus.on(Events.REPEATER_CHANGED, ({repeater}) => initComboboxes(repeater));
