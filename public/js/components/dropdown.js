import { Dropdown } from 'flowbite';

const instances = new WeakMap();

export default function initDropdowns(rootNode) {
    rootNode.querySelectorAll('[data-dropdown]').forEach(($triggerEl) => {
        const $dropdownEl = document.getElementById($triggerEl.getAttribute('data-dropdown'));
        const placement = $triggerEl.getAttribute('data-dropdown-placement');
        const offsetSkidding = $triggerEl.getAttribute('data-dropdown-offset-skidding');
        const offsetDistance = $triggerEl.getAttribute('data-dropdown-offset-distance');
        const triggerType = $triggerEl.getAttribute('data-dropdown-trigger');
        const delay = $triggerEl.getAttribute('data-dropdown-delay');
        const ignoreClickOutsideClass = $triggerEl.getAttribute('data-dropdown-ignore-click-outside-class');

        const dropdown = new Dropdown($dropdownEl, $triggerEl, {
            placement: placement ? placement : 'bottom',
            triggerType: triggerType ? triggerType : 'click',
            offsetSkidding: offsetSkidding ? parseInt(offsetSkidding) : 0,
            offsetDistance: offsetDistance ? parseInt(offsetDistance) : 10,
            delay: delay ? parseInt(delay) : 300,
            ignoreClickOutsideClass: ignoreClickOutsideClass
                ? ignoreClickOutsideClass
                : false,
        });

        if (!$dropdownEl.hasAttribute('data-close-dropdown-bound')) {
            $dropdownEl.setAttribute('data-close-dropdown-bound', '');
            $dropdownEl.addEventListener('click', (e) => {
                if (!e.target.closest('[data-close-dropdown]')) return;
                instances.get($dropdownEl)?.hide();
            });
        }
        instances.set($dropdownEl, dropdown);
    });
}
