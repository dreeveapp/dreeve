const AUTO_DISMISS_MS = 4000;

const KNOWN_TYPES = ['success', 'error', 'warning', 'info'];

const ensureContainer = () => {
    let container = document.getElementById('flash-toast-container');
    if (!container) {
        container = document.createElement('div');
        container.id = 'flash-toast-container';
        container.className = 'flash-toast-container';
        container.addEventListener('click', (event) => {
            const close = event.target.closest('.flash-toast .close');
            if (close) {
                removeToast(close.closest('.flash-toast'));
            }
        });
        document.body.appendChild(container);
    }
    return container;
};

const removeToast = (toast) => {
    if (!toast.isConnected) {
        return;
    }
    toast.classList.add('flash-toast--dismissing');
    setTimeout(() => toast.remove(), 300);
};

const buildToast = (type, message) => {
    const variant = KNOWN_TYPES.includes(type) ? type : 'success';

    const toast = document.createElement('div');
    toast.className = `flash-toast flash-toast--${variant}`;
    toast.setAttribute('role', 'alert');

    const icon = document.createElement('div');
    icon.className = `icon icon--${variant}`;

    const body = document.createElement('div');
    body.className = 'body';
    body.textContent = message;

    const close = document.createElement('button');
    close.type = 'button';
    close.className = 'close unstyled';
    close.setAttribute('aria-label', 'Close');

    toast.append(icon, body, close);
    return toast;
};

export const showToast = (type, message) => {
    const toast = buildToast(type, message);
    ensureContainer().appendChild(toast);
    setTimeout(() => removeToast(toast), AUTO_DISMISS_MS);
}

export default function initToasts(root = document) {
    const holder = root.querySelector('#flash-messages');
    if (!holder) {
        return;
    }

    let messages;
    try {
        messages = JSON.parse(holder.getAttribute('data-messages') || '{}');
    } catch (error) {
        return;
    }

    const entries = Object.entries(messages || {});
    if (0 === entries.length) {
        return;
    }

    const container = ensureContainer();

    entries.forEach(([type, texts]) => {
        (texts || []).forEach((text) => {
            const toast = buildToast(type, text);
            container.appendChild(toast);
            setTimeout(() => removeToast(toast), AUTO_DISMISS_MS);
        });
    });
}
