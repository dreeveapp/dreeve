import {showError, hideError} from "./form-error";

const fillFields = async (button) => {
    const form = button.closest('form');
    const errorBox = form?.querySelector('[data-form-error]');
    hideError(errorBox);
    button.classList.add('is-loading');
    button.disabled = true;

    try {
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
        if (!csrfToken) {
            throw new Error('csrf-token expected to be found');
        }

        const response = await fetch(button.getAttribute('data-fill-fields'), {
            method: 'POST',
            headers: {'X-CSRF-TOKEN': csrfToken},
            body: form ? new FormData(form) : null,
        });
        const result = await response.json().catch(() => ({}));
        if (!response.ok) {
            throw new Error(result.error ?? `Failed to fill fields: ${response.status}`);
        }

        Object.entries(result.fields ?? {}).forEach(([name, value]) => {
            const field = form?.elements.namedItem(name);
            if (!field) {
                return;
            }
            field.value = value;
            field.dispatchEvent(new Event('input', {bubbles: true}));
            field.dispatchEvent(new Event('change', {bubbles: true}));
        });
    } catch (error) {
        showError(errorBox, error.message);
    } finally {
        button.classList.remove('is-loading');
        button.disabled = false;
    }
};

export default function initFillFields() {
    document.addEventListener('click', (event) => {
        const button = event.target.closest('button[data-fill-fields]');
        if (!button) {
            return;
        }

        event.preventDefault();
        fillFields(button);
    });
}
