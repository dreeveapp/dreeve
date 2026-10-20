export default function initCheckboxGroupToggles() {
    document.addEventListener('click', (event) => {
        const toggle = event.target.closest('[data-checkbox-toggle]');
        if (!toggle) return;

        const scope = toggle.closest('[data-checkbox-toggle-scope]');
        if (!scope) return;

        const checked = toggle instanceof HTMLInputElement && 'checkbox' === toggle.type
            ? toggle.checked
            : 'all' === toggle.getAttribute('data-checkbox-toggle');
        scope.querySelectorAll('input[type="checkbox"]:not(:disabled)').forEach((box) => {
            if (box.checked === checked) return;
            box.checked = checked;
            box.dispatchEvent(new Event('change', {bubbles: true}));
        });
    });
}
