import {eventBus, Events} from "./event-bus";
import {basePath} from "../utils";
import {beginScrollRestore, rememberScrollPosition} from "./scroll-memory";

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

    toPath(url) {
        return url.slice(basePath().length).replace(/^\/+/, '');
    }

    determineContentUrl(page) {
        const path = this.toPath(page);
        const fragmentPath = /^[a-zA-Z0-9_\-\/]+$/.test(path) ? path : 'not-found';

        return `${basePath()}/api/fragment/page/${fragmentPath}`;
    }

    async renderContent(page, modalId, restoreScroll = false) {
        const contentUrl = this.determineContentUrl(page);

        rememberScrollPosition(this.app.getAttribute('data-router-current'));

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

        this.hideLoader();
        const scrollY = beginScrollRestore(page, restoreScroll);

        const fullPageName = this.toPath(page).replaceAll('/', '-');

        eventBus.emit(Events.PAGE_LOADED, {page: fullPageName, modalId});
        window.scrollTo(0, scrollY);
    }

    registerNavigation() {
        document.addEventListener('click', async e => {
            const link = e.target.closest?.('a[data-router-content-url]');
            if (!link) return;

            e.preventDefault();
            const route = link.getAttribute('href');

            await eventBus.emitAsync(Events.NAVIGATION_CLICKED, {link});

            this.navigateTo(
                route,
                null,
                link.hasAttribute('data-router-force-reload')
            );
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

            this.renderContent(e.state.route, e.state.modal, true);
        };
    }

    navigateTo(route, modal, force = false) {
        const currentRoute = this.app.getAttribute('data-router-current');
        if (currentRoute === route && !force) return; // Avoid reloading same page.

        this.renderContent(route, modal);
        this.pushRouteToHistoryState(route, modal);
    }

    pushRouteToHistoryState(route, modal) {
        window.history.pushState({route, modal}, '', this.buildUrl(route, modal));
    }

    buildUrl(route, modal) {
        return modal ? `${route}?modal=${modal}` : route;
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
        const modal = new URLSearchParams(location.search).get('modal') ?? '';

        this.registerNavigation();
        this.registerBrowserBackAndForth();
        this.renderContent(route, modal);

        window.history.replaceState({route, modal}, '', this.buildUrl(route, modal));
    }
}
