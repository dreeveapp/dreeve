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
            'nav a[data-router-link], aside li a[data-router-link]'
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
        const activeMenuLink = this.toPath(url).split('/')[0];

        return document.querySelector(`aside li a[href="${basePath()}/${activeMenuLink}"][data-router-link]`);
    }

    toPath(url) {
        return url.slice(basePath().length).replace(/^\/+/, '');
    }

    determineContentUrl(page) {
        const {baseUrl, pathPattern} = window.dreeve.pageFragment;
        const path = this.toPath(page);
        const fragmentPath = new RegExp(`^${pathPattern}$`).test(path) ? path : 'not-found';

        return `${baseUrl}/${fragmentPath}`;
    }

    async renderContent(page, restoreScroll = false) {
        const contentUrl = this.determineContentUrl(page);

        rememberScrollPosition(this.app.getAttribute('data-router-current'));

        // Close mobile nav if open
        if (!this.menu.hasAttribute('aria-hidden')) {
            this.mobileNavTriggerEl.dispatchEvent(
                new MouseEvent('click', {bubbles: true, cancelable: true, view: window})
            );
        }

        this.app.setAttribute('data-router-current', page);

        // Update active states
        this.menuItems.forEach(node => node.setAttribute('aria-selected', 'false'));
        this.determineActiveMenuLink(page)?.setAttribute('aria-selected', 'true');

        this.showLoader();

        const response = await fetch(contentUrl, {cache: 'no-store'});
        this.appContent.innerHTML = await response.text();

        this.hideLoader();
        const scrollY = beginScrollRestore(page, restoreScroll);

        const fullPageName = this.toPath(page).replaceAll('/', '-');

        eventBus.emit(Events.PAGE_LOADED, {page: fullPageName});
        window.scrollTo(0, scrollY);
    }

    registerNavigation() {
        document.addEventListener('click', async e => {
            const link = e.target.closest?.('a[data-router-link]');
            if (!link) return;

            e.preventDefault();
            const route = link.getAttribute('href');

            await eventBus.emitAsync(Events.NAVIGATION_CLICKED, {link});

            this.navigateTo(route, link.hasAttribute('data-router-force-reload'));
        });
    }

    registerBrowserBackAndForth() {
        window.onpopstate = e => {
            if (!e.state) return;

            this.renderContent(e.state.route, true);
        };
    }

    navigateTo(route, force = false) {
        const currentRoute = this.app.getAttribute('data-router-current');
        if (currentRoute === route && !force) return; // Avoid reloading same page.

        this.renderContent(route);
        window.history.pushState({route}, '', route);
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

        this.registerNavigation();
        this.registerBrowserBackAndForth();
        this.renderContent(route);

        window.history.replaceState({route}, '', route);
    }
}
