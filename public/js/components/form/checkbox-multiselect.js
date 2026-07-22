import {Dropdown} from 'flowbite';
import {eventBus, Events} from '../../core/event-bus';

const MAX_VISIBLE_CHIPS = 3;

const instances = new WeakMap();

class CheckboxMultiselect {
    constructor(root) {
        this.root = root;
        this.trigger = root.querySelector('[data-multiselect-trigger]');
        this.panel = root.querySelector('[data-multiselect-panel]');
        this.chips = root.querySelector('[data-multiselect-chips]');
        this.search = root.querySelector('[data-multiselect-search]');
        this.errorBox = root.querySelector('[data-multiselect-error]');
        this.placeholder = root.getAttribute('data-multiselect-placeholder') || '';
        this.requiredMessage = root.getAttribute('data-multiselect-required');
    }

    init() {
        this.dropdown = new Dropdown(this.panel, this.trigger, {
            placement: 'bottom-start',
            offsetDistance: 6,
            onShow: () => {
                this.trigger.setAttribute('aria-expanded', 'true');
                this.search.focus();
            },
            onHide: () => {
                this.trigger.setAttribute('aria-expanded', 'false');
                this.search.value = '';
                this.applyFilter();
            },
        });

        // Flowbite only listens for clicks; a div[role=button] needs Enter/Space itself.
        this.trigger.addEventListener('keydown', (event) => {
            if ('Enter' !== event.key && ' ' !== event.key) return;
            event.preventDefault();
            this.dropdown.toggle();
        });

        this.trigger.addEventListener('click', (event) => {
            const removeButton = event.target.closest('[data-multiselect-chip-remove]');
            if (!removeButton) return;
            event.stopPropagation();
            const value = removeButton.getAttribute('data-multiselect-chip-remove');
            this.checkboxes().forEach((box) => {
                if (box.value === value) box.checked = false;
            });
            this.render();
        });

        this.root.addEventListener('change', (event) => {
            if (!(event.target instanceof HTMLInputElement) || 'checkbox' !== event.target.type) return;
            this.render();
        });

        this.search.addEventListener('input', () => this.applyFilter());
        this.search.addEventListener('keydown', (event) => {
            if ('Enter' === event.key) event.preventDefault();
        });

        this.panel.querySelector('[data-multiselect-all]').addEventListener('click', () => this.setAll(true));
        this.panel.querySelector('[data-multiselect-clear]').addEventListener('click', () => this.setAll(false));

        this.render();
    }

    checkboxes() {
        return Array.from(this.panel.querySelectorAll('input[type="checkbox"]'));
    }

    setAll(checked) {
        this.checkboxes().forEach((box) => box.checked = checked);
        this.render();
    }

    applyFilter() {
        const term = this.search.value.trim().toLowerCase();
        this.panel.querySelectorAll('ul > li').forEach((row) => {
            row.classList.toggle('hidden', '' !== term && !row.textContent.toLowerCase().includes(term));
        });
    }

    render() {
        const checked = this.checkboxes().filter((box) => box.checked);
        this.chips.textContent = '';

        if (0 === checked.length) {
            const placeholder = document.createElement('span');
            placeholder.className = 'checkbox-multiselect__placeholder';
            placeholder.textContent = this.placeholder;
            this.chips.appendChild(placeholder);
        }

        checked.slice(0, MAX_VISIBLE_CHIPS).forEach((box) => {
            const labelText = box.closest('label')?.textContent.trim() ?? box.value;
            const chip = document.createElement('span');
            chip.className = 'checkbox-multiselect__chip';
            const label = document.createElement('span');
            label.textContent = labelText;
            const remove = document.createElement('button');
            remove.type = 'button';
            remove.className = 'unstyled';
            remove.setAttribute('data-multiselect-chip-remove', box.value);
            remove.setAttribute('aria-label', labelText);
            chip.append(label, remove);
            this.chips.appendChild(chip);
        });

        if (checked.length > MAX_VISIBLE_CHIPS) {
            const more = document.createElement('span');
            more.className = 'checkbox-multiselect__more';
            more.textContent = `+${checked.length - MAX_VISIBLE_CHIPS}`;
            this.chips.appendChild(more);
        }

        if (checked.length > 0) {
            this.clearError();
        }
    }

    validate() {
        if (!this.requiredMessage) return true;
        const boxes = this.checkboxes();
        // Disabled checkboxes sit inside a hidden [data-dependent-on] container;
        // mirror native constraint validation and skip them.
        if (boxes.every((box) => box.disabled)) return true;
        if (boxes.some((box) => box.checked)) {
            this.clearError();
            return true;
        }
        this.root.classList.add('has-error');
        this.errorBox.textContent = this.requiredMessage;
        this.errorBox.classList.remove('hidden');
        return false;
    }

    clearError() {
        this.root.classList.remove('has-error');
        this.errorBox.textContent = '';
        this.errorBox.classList.add('hidden');
    }
}

export const validateMultiselects = (form) => Array.from(form.querySelectorAll('[data-multiselect]'))
    .map((root) => instances.get(root)?.validate() ?? true)
    .every(Boolean);

export default function initCheckboxMultiselects(rootNode = document) {
    rootNode.querySelectorAll('[data-multiselect]').forEach((root) => {
        if (instances.has(root)) return;
        const instance = new CheckboxMultiselect(root);
        instances.set(root, instance);
        instance.init();
    });
}

eventBus.on(Events.REPEATER_CHANGED, ({repeater}) => initCheckboxMultiselects(repeater));
