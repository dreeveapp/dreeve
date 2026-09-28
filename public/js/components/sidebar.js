import {basePath} from "../utils";

export default function initSidebar() {
    document.getElementById('toggle-sidebar-collapsed-state')?.addEventListener('click', () => {
        const collapsed = document.documentElement.toggleAttribute('data-sidebar-collapsed');
        localStorage.setItem('sideNavCollapsed', String(collapsed));
    });

    const section = location.pathname.slice(basePath().length).replace(/^\/+/, '').split('/')[0] || 'dashboard';
    document.querySelector(`aside li a[href="${basePath()}/${section}"]`)?.setAttribute('aria-selected', 'true');
}
