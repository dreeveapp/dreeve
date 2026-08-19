import { Tabs } from 'flowbite';

export default function initTabs(rootNode) {
    rootNode.querySelectorAll('[data-tabs]').forEach(($triggerEl) => {
        const tabItems = [];
        let defaultTabId = null;

        $triggerEl
            .querySelectorAll('[role="tab"]')
            .forEach(function ($triggerEl) {
                const dataTabsTarget = $triggerEl.getAttribute('data-tabs-target');
                tabItems.push({
                    id: dataTabsTarget,
                    triggerEl: $triggerEl,
                    targetEl: document.querySelector(dataTabsTarget),
                });
                if ($triggerEl.hasAttribute('data-tab-default')) {
                    defaultTabId = dataTabsTarget;
                }
            });

        new Tabs($triggerEl, tabItems, {
            defaultTabId: defaultTabId,
            activeClasses: 'active',
            inactiveClasses: 'inactive',
        });
    });
}
