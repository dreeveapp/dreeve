const initializedTabLists = new WeakSet();

const showTab = ($tabs, $activeTab) => {
    $tabs.forEach(($tab) => {
        const isActive = $tab === $activeTab;
        $tab.classList.toggle('active', isActive);
        $tab.classList.toggle('inactive', !isActive);
        $tab.setAttribute('aria-selected', isActive ? 'true' : 'false');
        document.querySelector($tab.getAttribute('data-tabs-target'))?.classList.toggle('hidden', !isActive);
    });
};

export default function initTabs(rootNode) {
    rootNode.querySelectorAll('[data-tabs]').forEach(($tabList) => {
        if (initializedTabLists.has($tabList)) {
            return;
        }

        const $tabs = [...$tabList.querySelectorAll('[role="tab"]')];
        if (!$tabs.length) {
            return;
        }

        initializedTabLists.add($tabList);

        showTab($tabs, $tabs.find(($tab) => $tab.getAttribute('aria-selected') === 'true') ?? $tabs[0]);

        $tabs.forEach(($tab) => {
            $tab.addEventListener('click', (event) => {
                event.preventDefault();
                showTab($tabs, $tab);
            });
        });
    });
}
