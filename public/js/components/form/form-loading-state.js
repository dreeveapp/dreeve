export default function initFormLoadingState() {
    document.addEventListener('submit', function (event) {
        const form = event.target;
        if (!(form instanceof HTMLFormElement)) {
            return;
        }

        form.querySelectorAll('button[type="submit"][data-has-loading-state]').forEach(function (button) {
            button.classList.add('is-loading');
            button.disabled = true;
        });
    });
}
