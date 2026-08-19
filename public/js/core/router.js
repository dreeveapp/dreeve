import {eventBus, Events} from "./event-bus";
import {basePath} from "../utils";
import {beginScrollRestore, rememberScrollPosition} from "./scroll-memory";

const DEFAULT_ROUTE = '/dashboard';

class Router {
    constructor() {
        this._booted = false;
        this._pendingNavigation = null;
    }

    boot() {
        if (this._booted) return;
        this._booted = true;

        const route = this._currentRoute();

        this._registerNavigation();
        this._registerBrowserBackAndForth();
        this._renderContent(route);

        window.history.replaceState({route}, '', route);
    }

    navigateTo(route) {
        const currentRoute = this._app().getAttribute('data-router-current');
        if (currentRoute === route) return; // Avoid reloading same page.

        this._renderContent(route);
        window.history.pushState({route}, '', route);
    }

    replaceQuery(queryString) {
        const path = (this._app().getAttribute('data-router-current') ?? '').split('?')[0];
        const route = queryString ? `${path}?${queryString}` : path;

        this._app().setAttribute('data-router-current', route);
        window.history.replaceState({route}, '', route);
    }

    _app() {
        return document.querySelector('main');
    }

    _appContent() {
        return this._app().querySelector('#js-loaded-content');
    }

    _spinner() {
        return this._app().querySelector('#spinner');
    }

    _menu() {
        return document.querySelector('aside');
    }

    _menuItems() {
        return document.querySelectorAll('nav a[data-router-link], aside li a[data-router-link]');
    }

    _mobileNavTriggerEl() {
        return document.querySelector('[data-drawer-target="drawer-navigation"]');
    }

    _showLoader() {
        this._spinner().classList.remove('hidden');
        this._spinner().classList.add('flex');
        this._appContent().classList.add('hidden');
    }

    _hideLoader() {
        this._spinner().classList.remove('flex');
        this._spinner().classList.add('hidden');
        this._appContent().classList.remove('hidden');
    }

    _determineActiveMenuLink(url) {
        const activeMenuLink = this._toPath(url).split('/')[0];

        return document.querySelector(`aside li a[href="${basePath()}/${activeMenuLink}"][data-router-link]`);
    }

    _toPath(url) {
        return url.split('?')[0].slice(basePath().length).replace(/^\/+/, '');
    }

    _determineContentUrl(page) {
        const {baseUrl, pathPattern} = window.dreeve.pageFragment;
        const path = this._toPath(page);
        const fragmentPath = new RegExp(`^${pathPattern}$`).test(path) ? path : 'not-found';

        return `${baseUrl}/${fragmentPath}`;
    }

    _closeMobileNav() {
        if (this._menu().hasAttribute('aria-hidden')) return;

        this._mobileNavTriggerEl().dispatchEvent(
            new MouseEvent('click', {bubbles: true, cancelable: true, view: window})
        );
    }

    async _renderContent(page, restoreScroll = false) {
        const contentUrl = this._determineContentUrl(page);

        const previousRoute = this._app().getAttribute('data-router-current');

        rememberScrollPosition(previousRoute);
        this._closeMobileNav();

        this._app().setAttribute('data-router-current', page);

        // Update active states
        this._menuItems().forEach(node => node.setAttribute('aria-selected', 'false'));
        this._determineActiveMenuLink(page)?.setAttribute('aria-selected', 'true');

        this._showLoader();

        // A newer navigation always wins: abort whatever is still in flight.
        this._pendingNavigation?.abort();
        const navigation = new AbortController();
        this._pendingNavigation = navigation;

        let html;
        try {
            const response = await fetch(contentUrl, {cache: 'no-store', signal: navigation.signal});

            // A missing fragment is answered with a rendered not-found page, so 404 carries
            // a body we want. Anything else is a genuine failure.
            if (!response.ok && response.status !== 404) {
                throw new Error(`Fragment request failed with status ${response.status}`);
            }

            html = await response.text();
        } catch (e) {
            if (e.name === 'AbortError') return;
            if (this._pendingNavigation !== navigation) return; // A newer navigation owns the UI now.

            console.error(`Could not load ${contentUrl}`, e);
            this._pendingNavigation = null;
            // Nothing was rendered, so keep pointing at the page still on screen.
            this._app().setAttribute('data-router-current', previousRoute ?? '');
            this._hideLoader();
            return;
        }

        if (this._pendingNavigation !== navigation) return;
        this._pendingNavigation = null;

        this._appContent().innerHTML = html;

        this._hideLoader();
        const scrollY = beginScrollRestore(page, restoreScroll);

        const fullPageName = this._toPath(page).replaceAll('/', '-');

        eventBus.emit(Events.PAGE_LOADED, {page: fullPageName});
        window.scrollTo(0, scrollY);
    }

    _registerNavigation() {
        document.addEventListener('click', e => {
            const link = e.target.closest?.('a[data-router-link]');
            if (!link) return;

            e.preventDefault();

            this.navigateTo(link.getAttribute('href'));
        });
    }

    _registerBrowserBackAndForth() {
        window.addEventListener('popstate', e => {
            if (!e.state) return;

            this._renderContent(e.state.route, true);
        });
    }

    _currentRoute() {
        const base = basePath();
        const path = '' === base
            ? (location.pathname.replace('/', '') ? location.pathname : DEFAULT_ROUTE)
            : (location.pathname.replace(/\/+$/, '') === base ? base + DEFAULT_ROUTE : location.pathname);

        return path + location.search;
    }
}

export const router = new Router();
