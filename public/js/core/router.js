import {eventBus, Events} from "./event-bus";
import {basePath} from "../utils";

export default class Router {
    constructor(app) {
        this.app = app;
        this.appContent = app.querySelector('#js-loaded-content');
        this.spinner = app.querySelector('#spinner');
        this.menu = document.querySelector('aside');
        this.menuItems = document.querySelectorAll(
            'nav a[data-router-content-url], aside li a[data-router-content-url]'
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
        const activeLink = document.querySelector(`aside li a[href="${url}"][data-router-content-url]`);
        if (activeLink) {
            return activeLink;
        }

        const newUrl = url.replace(/\/[^\/]*$/, '');
        if (newUrl === url || newUrl === '') {
            return null;
        }

        return this.determineActiveMenuLink(newUrl);
    }

    determineContentUrlLink(url) {
        const link = document.querySelector(`a[href="${url}"][data-router-content-url]`);
        if (link) {
            return {link: link, route: url};
        }

        const newUrl = url.replace(/\/[^\/]*$/, '');
        if (newUrl === url || newUrl === '') {
            return null;
        }

        return this.determineContentUrlLink(newUrl);
    }

    determineContentUrl(page) {
        const match = this.determineContentUrlLink(page);

        return match ? match.link.getAttribute('data-router-content-url') + page.slice(match.route.length) : null;
    }

    async renderContent(page, modalId) {
        const contentUrl = this.determineContentUrl(page) ?? `${basePath()}/api/fragment/page/not-found`;

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

        this.determineActiveMenuLink(page)?.setAttribute('aria-selected', 'true');

        this.showLoader();

        const response = await fetch(contentUrl, {cache: 'no-store'});
        this.appContent.innerHTML = await response.text();
        window.scrollTo(0, 0);

        this.hideLoader();

        // Re-register nav items that may have been added dynamically
        const newNavItems = document.querySelectorAll('main a[data-router-content-url]');
        this.registerNavItems(newNavItems);

        const fullPageName = page
            .slice(basePath().length)
            .replace(/^\/+/, '')
            .replaceAll('/', '-');

        eventBus.emit(Events.PAGE_LOADED, {page: fullPageName, modalId});
    }

    registerNavItems(items) {
        items.forEach(link => {
            link.addEventListener('click', async e => {
                e.preventDefault();
                const route = link.getAttribute('href');

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
        const base = basePath();
        if ('' === base) {
            return location.pathname.replace('/', '') ? location.pathname : defaultRoute;
        }

        return location.pathname.replace(/\/+$/, '') === base
            ? base + defaultRoute
            : location.pathname;
    }

    boot() {
        const route = this.currentRoute();
        const modal = location.hash.replace('#', '');

        this.registerNavItems(this.menuItems);
        this.registerBrowserBackAndForth();
        this.renderContent(route, modal);

        window.history.replaceState({route, modal}, '', route + location.hash);
    }
}
