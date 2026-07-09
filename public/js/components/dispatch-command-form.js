import {dispatchCommand} from "../utils";

// Turns a field name into its path segments:
//   "label"            -> ["label"]
//   "attachedTo[]"     -> ["attachedTo", ""]
//   "a[b][0][c]"       -> ["a", "b", "0", "c"]
const pathOf = (name) => {
    const segments = [name.replace(/\[.*$/, '')];
    const matches = name.matchAll(/\[([^\]]*)\]/g);
    for (const [, segment] of matches) {
        segments.push(segment);
    }
    return segments;
};

// Assigns a value into a (possibly nested) payload based on bracket-notation
// path segments. An empty segment ("[]") appends, a numeric segment builds an
// array, anything else builds an object.
const assign = (target, segments, value) => {
    let cursor = target;
    segments.forEach((segment, index) => {
        const isLast = index === segments.length - 1;
        if (isLast) {
            if ('' === segment) {
                cursor.push(value);
            } else {
                cursor[segment] = value;
            }
            return;
        }

        const nextSegment = segments[index + 1];
        const container = ('' === nextSegment || /^\d+$/.test(nextSegment)) ? [] : {};

        if ('' === segment) {
            const created = container;
            cursor.push(created);
            cursor = created;
        } else {
            if (undefined === cursor[segment]) {
                cursor[segment] = container;
            }
            cursor = cursor[segment];
        }
    });
};

const INDEX_TOKEN = '__index__';
const INDEXED_ATTRIBUTES = ['name', 'id', 'for'];

class Repeater {
    constructor(root) {
        this.root = root;
        this.list = root.querySelector('[data-repeater-list]');
        this.template = root.querySelector('[data-repeater-template]');
        this.addButton = root.querySelector('[data-repeater-add]');
        this.min = parseInt(root.getAttribute('data-repeater-min') ?? '0', 10) || 0;
    }

    init() {
        if (!this.list || !this.template) {
            return;
        }

        const initial = JSON.parse(this.list.getAttribute('data-repeater-initial') || '[]');
        initial.forEach((data) => this.addRow(data));

        while (this.rows().length < this.min) {
            this.addRow(null);
        }

        this.addButton?.addEventListener('click', () => this.addRow(null));
        this.list.addEventListener('click', (event) => {
            const removeButton = event.target.closest('[data-repeater-remove]');
            if (removeButton) {
                this.removeRow(removeButton.closest('[data-repeater-item]'));
            }
        });
    }

    rows() {
        return Array.from(this.list.querySelectorAll('[data-repeater-item]'));
    }

    addRow(data) {
        const row = this.template.content.firstElementChild.cloneNode(true);

        row.querySelectorAll('[name], [id], [for]').forEach((field) => {
            INDEXED_ATTRIBUTES.forEach((attribute) => {
                const value = field.getAttribute(attribute);
                if (null !== value && value.includes(INDEX_TOKEN)) {
                    field.dataset[`${attribute}Template`] = value;
                }
            });
        });

        if (data) {
            row.querySelectorAll('[data-repeater-field]').forEach((field) => {
                const value = field.dataset.repeaterField.split('.').reduce((value, key) => (null == value ? undefined : value[key]), data);
                if (undefined !== value && null !== value) {
                    if ('checkbox' === field.type) {
                        // Boolean checkbox (true/false) or a checkbox group seeded from an array of values.
                        field.checked = Array.isArray(value) ? value.includes(field.value) : Boolean(value);
                    } else {
                        field.value = value;
                    }
                }
            });
        }

        this.list.appendChild(row);
        this.reindex();
    }

    removeRow(row) {
        if (this.rows().length <= this.min) {
            return;
        }
        row.remove();
        this.reindex();
    }

    reindex() {
        const rows = this.rows();

        const removable = rows.length > this.min;
        rows.forEach((row, index) => {
            row.querySelectorAll('[data-name-template], [data-id-template], [data-for-template]').forEach((field) => {
                INDEXED_ATTRIBUTES.forEach((attribute) => {
                    const template = field.dataset[`${attribute}Template`];
                    if (template) {
                        field.setAttribute(attribute, template.replaceAll(INDEX_TOKEN, index));
                    }
                });
            });
            row.querySelector('[data-repeater-remove]')?.classList.toggle('hidden', !removable);
        });
    }
}

const showError = (box, message) => {
    if (!box) return;
    box.textContent = message;
    box.classList.remove('hidden');
};

const hideError = (box) => {
    if (!box) return;
    box.textContent = '';
    box.classList.add('hidden');
};

const resetLoading = (buttons) => {
    buttons.forEach((button) => {
        button.classList.remove('is-loading');
        button.disabled = false;
    });
};

const syncCheckboxGroupValidity = (group) => {
    const boxes = group.querySelectorAll('input[type="checkbox"]');
    if (!boxes.length) return;
    const message = [...boxes].some((box) => box.checked)
        ? ''
        : (group.dataset.requiredCheckboxGroup || 'Please select at least one option.');
    boxes[0].setCustomValidity(message);
};

export default function initDispatchCommandForm(rootNode = document) {
    rootNode.querySelectorAll('[data-dispatch-command] [data-repeater]').forEach((root) => new Repeater(root).init());

    rootNode.addEventListener('change', (event) => {
        const target = event.target;
        if (target instanceof HTMLInputElement && 'checkbox' === target.type) {
            const group = target.closest('[data-required-checkbox-group]');
            if (group) syncCheckboxGroupValidity(group);
        }
    });

    rootNode.addEventListener('submit', async (event) => {
        const form = event.target;
        if (!(form instanceof HTMLFormElement) || !form.matches('[data-dispatch-command]')) {
            return;
        }

        event.preventDefault();

        const commandName = form.getAttribute('data-dispatch-command');
        const errorBox = form.querySelector('[data-form-error]');
        const loadingButtons = form.querySelectorAll('button[data-has-loading-state]');

        // Required checkbox groups aren't native-required fields, so the submit event fires even when
        // they're empty (and the loading state has already been applied); gate on them here.
        form.querySelectorAll('[data-required-checkbox-group]').forEach(syncCheckboxGroupValidity);
        if (!form.reportValidity()) {
            resetLoading(loadingButtons);
            return;
        }

        hideError(errorBox);

        try {
            const payload = {};
            new FormData(form).forEach((value, key) => {
                assign(payload, pathOf(key), value);
            });

            await dispatchCommand(commandName, payload);

            const redirectUrl = form.getAttribute('data-redirect');
            if (redirectUrl) {
                window.location.assign(redirectUrl);
            } else {
                window.location.reload();
            }
        } catch (error) {
            showError(errorBox, error.message);
            window.scrollTo(0, 0);
            resetLoading(loadingButtons);
        }
    });
}
