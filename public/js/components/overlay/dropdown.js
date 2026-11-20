import anchor from './floating';

const initialisedTriggers = new WeakSet();

export default function initDropdowns(rootNode) {
    rootNode.querySelectorAll('[data-dropdown]').forEach(($triggerEl) => {
        if (initialisedTriggers.has($triggerEl)) return;

        const $dropdownEl = document.getElementById($triggerEl.getAttribute('data-dropdown'));
        if (!$dropdownEl) return;

        const placement = $triggerEl.getAttribute('data-dropdown-placement') || 'bottom';
        const offset = parseInt($triggerEl.getAttribute('data-dropdown-offset-distance') ?? 10);
        const skidding = parseInt($triggerEl.getAttribute('data-dropdown-offset-skidding') ?? 0);
        let cleanup = null;
        let wasOpenOnPointerDown = false;

        $triggerEl.setAttribute('aria-expanded', 'false');
        $triggerEl.setAttribute('aria-controls', $dropdownEl.id);

        $triggerEl.addEventListener('pointerdown', () => {
            wasOpenOnPointerDown = $dropdownEl.matches(':popover-open');
        });
        $triggerEl.addEventListener('click', (e) => {
            const wasOpen = e.detail === 0 ? $dropdownEl.matches(':popover-open') : wasOpenOnPointerDown;
            if (wasOpen) {
                if ($dropdownEl.matches(':popover-open')) $dropdownEl.hidePopover();
                return;
            }

            if ($dropdownEl.matches(':popover-open')) return;

            $dropdownEl.showPopover();
            cleanup?.();
            cleanup = anchor({
                reference: $triggerEl,
                floating: $dropdownEl,
                placement,
                offset,
                skidding,
            });
        });
        $dropdownEl.addEventListener('toggle', (e) => {
            $triggerEl.setAttribute('aria-expanded', String(e.newState === 'open'));
            if (e.newState === 'open') return;

            cleanup?.();
            cleanup = null;
        });
        $dropdownEl.addEventListener('click', (e) => {
            if (!e.target.closest('[data-close-dropdown]')) return;
            $dropdownEl.hidePopover();
        });
        initialisedTriggers.add($triggerEl);
    });
}
