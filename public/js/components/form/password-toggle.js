export default function initPasswordToggle() {
    document.querySelectorAll('[data-toggle-password]').forEach((button) => {
        const input = document.getElementById(button.getAttribute('data-toggle-password'));
        if (!input) {
            return;
        }

        const iconShow = button.querySelector('[data-icon-show]');
        const iconHide = button.querySelector('[data-icon-hide]');

        button.addEventListener('click', () => {
            const isHidden = input.type === 'password';
            input.type = isHidden ? 'text' : 'password';

            if (iconShow) {
                iconShow.classList.toggle('hidden', isHidden);
            }
            if (iconHide) {
                iconHide.classList.toggle('hidden', !isHidden);
            }

            button.setAttribute('aria-label', isHidden ? 'Hide password' : 'Show password');
        });
    });
}
