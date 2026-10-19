const EDITABLE_SELECTOR = 'input, textarea, select, [contenteditable]:not([contenteditable="false"])';
const IGNORED_CONTAINER_SELECTOR = '.leaflet-container';
const OPEN_OVERLAY_SELECTOR = '[drawer-backdrop], [modal-backdrop], dialog[open]';

const shouldIgnore = (e) => {
    if (e.defaultPrevented || e.repeat || e.altKey || e.ctrlKey || e.metaKey || e.shiftKey) return true;
    if (e.target instanceof Element && e.target.closest(`${EDITABLE_SELECTOR}, ${IGNORED_CONTAINER_SELECTOR}`)) return true;
    if (document.documentElement.classList.contains('lg-on')) return true;

    return null !== document.querySelector(OPEN_OVERLAY_SELECTOR);
};

export default function initKeyboardShortcuts() {
    document.addEventListener('keydown', (e) => {
        if (shouldIgnore(e)) return;

        const element = document.querySelector(`[data-keyboard-shortcut="${CSS.escape(e.key)}"]`);
        if (!element) return;

        e.preventDefault();
        element.click();
    });
}
