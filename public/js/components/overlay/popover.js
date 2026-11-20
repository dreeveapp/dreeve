import anchor from './floating';

const initialisedTriggers = new WeakSet();

export default function initPopovers(rootNode) {
    rootNode.querySelectorAll('[data-popover-target]').forEach(($triggerEl) => {
        if (initialisedTriggers.has($triggerEl)) return;

        const $popoverEl = document.getElementById($triggerEl.getAttribute('data-popover-target'));
        if (!$popoverEl) return;

        const placement = $triggerEl.getAttribute('data-popover-placement') || 'top';
        let cleanup = null;
        let hideTimeout = null;

        const show = () => {
            clearTimeout(hideTimeout);
            if ($popoverEl.matches(':popover-open')) return;

            $popoverEl.showPopover();
            cleanup = anchor({
                reference: $triggerEl,
                floating: $popoverEl,
                placement,
                offset: 10,
            });
        };
        const hide = () => {
            clearTimeout(hideTimeout);
            hideTimeout = setTimeout(() => {
                if ($popoverEl.matches(':popover-open')) $popoverEl.hidePopover();
                cleanup?.();
                cleanup = null;
            }, 100);
        };

        [$triggerEl, $popoverEl].forEach(($el) => {
            ['mouseenter', 'focus'].forEach((event) => $el.addEventListener(event, show));
            ['mouseleave', 'blur'].forEach((event) => $el.addEventListener(event, hide));
        });
        initialisedTriggers.add($triggerEl);
    });
}
