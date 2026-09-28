export default function initSidebar() {
    document.getElementById('toggle-sidebar-collapsed-state')?.addEventListener('click', () => {
        const collapsed = document.documentElement.toggleAttribute('data-sidebar-collapsed');
        localStorage.setItem('sideNavCollapsed', String(collapsed));
    });
}
