import {eventBus, Events} from "./event-bus";

export default class Router {
    constructor(app) {
        this.app = app;
        this.appContent = app.querySelector('#js-loaded-content');
        this.spinner = app.querySelector('#spinner');
        this.menu = document.querySelector('aside');
        this.menuItems = document.querySelectorAll(
            'nav a[data-router-navigate]:not([data-router-disabled]), aside li a[data-router-navigate]:not([data-router-disabled])'
        );
        this.mobileNavTriggerEl = document.querySelector('[data-drawer-target="drawer-navigation"]');
    }

    showLoader() {
        this.spinner.classList.remove('hidden');
        this.spinner.classList.add('flex');
        this.appContent.classList.add('hidden');
    }

    hideLoader() {
        this.spinner.classList.remove('flex');
        this.spinner.classList.add('hidden');
        this.appContent.classList.remove('hidden');
    }

    determineActiveMenuLink(url) {
        const activeLink = document.querySelector(`aside li a[data-router-navigate="${url}"]`);
        if (activeLink) {
            return activeLink;
        }

        const newUrl = url.replace(/\/[^\/]*$/, '');
        if (newUrl === url || newUrl === '') {
            return null;
        }

        return this.determineActiveMenuLink(newUrl);
    }

    determineContentUrl(page) {
        // Server rendered pages carry their content URL on the link itself. Deliberately an exact match
        // and not determineActiveMenuLink()'s walk-up: a converted /gear must not hand its content URL
        // to a still static /gear/maintenance. No attribute means the page is still a static build file.
        const link = document.querySelector(
            `a[data-router-navigate="${page}"][data-router-content-url]`
        );

        return link?.getAttribute('data-router-content-url') ?? `${page}.html`;
    }

    async renderContent(page, modalId) {
        // Close mobile nav if open
        if (!this.menu.hasAttribute('aria-hidden')) {
            this.mobileNavTriggerEl.dispatchEvent(
                new MouseEvent('click', {bubbles: true, cancelable: true, view: window})
            );
        }

        this.app.setAttribute('data-router-current', page);
        this.app.setAttribute('data-modal-current', modalId);

        // Update active states
        this.menuItems.forEach(node => node.setAttribute('aria-selected', 'false'));

        const activeLink = this.determineActiveMenuLink(page);
        activeLink?.setAttribute('aria-selected', 'true');

        if (activeLink?.hasAttribute('data-router-sub-menu')) {
            activeLink.closest('ul')?.classList.remove('hidden');
        }

        this.showLoader();

        const response = await fetch(this.determineContentUrl(page), {cache: 'no-store'});
        this.appContent.innerHTML = await response.text();
        window.scrollTo(0, 0);

        this.hideLoader();

        // Re-register nav items that may have been added dynamically
        const newNavItems = document.querySelectorAll('main a[data-router-navigate]:not([data-router-disabled])');
        this.registerNavItems(newNavItems);

        const fullPageName = page
            .replace(window.dreeve.appUrl.basePath, '')
            .replace(/^\/+/, '')
            .replaceAll('/', '-');

        eventBus.emit(Events.PAGE_LOADED, {page: fullPageName, modalId});
    }

    registerNavItems(items) {
        items.forEach(link => {
            link.addEventListener('click', async e => {
                e.preventDefault();
                const route = link.getAttribute('data-router-navigate');

                await eventBus.emitAsync(Events.NAVIGATION_CLICKED, {link});

                this.navigateTo(
                    route,
                    null,
                    link.hasAttribute('data-router-force-reload')
                );
            });
        });
    }

    registerBrowserBackAndForth() {
        window.onpopstate = e => {
            if (!e.state) return;

            if (e.state.route === this.app.getAttribute('data-router-current')) {
                this.app.setAttribute('data-modal-current', e.state.modal);
                eventBus.emit(Events.MODAL_HISTORY_CHANGED, {modalId: e.state.modal});
                return;
            }

            this.renderContent(e.state.route, e.state.modal);
        };
    }

    navigateTo(route, modal, force = false) {
        const currentRoute = this.app.getAttribute('data-router-current');
        if (currentRoute === route && !force) return; // Avoid reloading same page.

        this.renderContent(route, modal);
        this.pushRouteToHistoryState(route, modal);
    }

    pushRouteToHistoryState(route, modal) {
        const fullRoute = modal ? `${route}#${modal}` : route;
        window.history.pushState({route, modal}, '', fullRoute);
    }

    pushCurrentRouteToHistoryState(modal) {
        this.pushRouteToHistoryState(this.currentRoute(), modal);
    }

    currentRoute() {
        const defaultRoute = '/dashboard';
        if (window.dreeve.appUrl.basePath === '') {
            // App is not served from a subpath.
            return location.pathname.replace('/', '') ? location.pathname : defaultRoute;
        }

        // App is served from a subpath.
        const base = '/' + window.dreeve.appUrl.basePath.replace(/^\/+|\/+$/g, '');
        const pathname = location.pathname.replace(/\/+$/, '');

        return pathname === base
            ? base + defaultRoute
            : location.pathname;
    }

    boot() {
        if (this.appContent === null) {
            // App content can be null if SYMFONY routing is used.
            return;
        }

        const route = this.currentRoute();
        const modal = location.hash.replace('#', '');

        this.registerNavItems(this.menuItems);
        this.registerBrowserBackAndForth();
        this.renderContent(route, modal);

        window.history.replaceState({route, modal}, '', route + location.hash);
    }
}
