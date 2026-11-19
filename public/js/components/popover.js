import { Popover } from 'flowbite';

const initialisedTriggers = new WeakSet();

export default function initPopovers(rootNode) {
    rootNode.querySelectorAll('[data-popover-target]').forEach(($triggerEl) => {
        if (initialisedTriggers.has($triggerEl)) return;

        const $popoverEl = document.getElementById($triggerEl.getAttribute('data-popover-target'));
        if (!$popoverEl) return;

        const placement = $triggerEl.getAttribute('data-popover-placement');
        const triggerType = $triggerEl.getAttribute('data-popover-trigger');

        new Popover($popoverEl, $triggerEl, {
            placement: placement ? placement : 'top',
            triggerType: triggerType ? triggerType : 'hover',
        });
        initialisedTriggers.add($triggerEl);
    });
}
