const TRIGGER_SELECTOR = '[data-drawer-toggle], [data-drawer-show], [data-drawer-hide]';

const drawerIdOf = ($trigger) => $trigger.dataset.drawerToggle ?? $trigger.dataset.drawerShow ?? $trigger.dataset.drawerHide;

const isOpen = ($drawer) => $drawer.getAttribute('aria-modal') === 'true';

const setOpen = ($drawer, open) => {
    if (open) {
        $drawer.setAttribute('aria-modal', 'true');
        $drawer.setAttribute('role', 'dialog');
        return;
    }

    $drawer.removeAttribute('aria-modal');
    $drawer.removeAttribute('role');
};

export default function initDrawers() {
    document.addEventListener('click', (event) => {
        const $trigger = event.target.closest(TRIGGER_SELECTOR);
        if (!$trigger) return;

        const $drawer = document.getElementById(drawerIdOf($trigger));
        if (!$drawer) return;

        if ($trigger.hasAttribute('data-drawer-toggle')) {
            setOpen($drawer, !isOpen($drawer));
            return;
        }

        setOpen($drawer, $trigger.hasAttribute('data-drawer-show'));
    });

    document.addEventListener('keydown', (event) => {
        if (event.key !== 'Escape') return;

        document.querySelectorAll(TRIGGER_SELECTOR).forEach(($trigger) => {
            const $drawer = document.getElementById(drawerIdOf($trigger));
            if ($drawer && isOpen($drawer)) {
                setOpen($drawer, false);
            }
        });
    });
}
