export default function initCheckboxGroupToggles() {
    document.addEventListener('click', (event) => {
        const button = event.target.closest('[data-checkbox-toggle]');
        if (!button) return;

        const scope = button.closest('[data-checkbox-toggle-scope]');
        if (!scope) return;

        const checked = 'all' === button.getAttribute('data-checkbox-toggle');
        scope.querySelectorAll('input[type="checkbox"]:not(:disabled)').forEach((box) => {
            if (box.checked === checked) return;
            box.checked = checked;
            box.dispatchEvent(new Event('change', {bubbles: true}));
        });
    });
}
