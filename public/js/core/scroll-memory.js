const MAX_REMEMBERED_ROUTES = 5;

const positionPerRoute = new Map();
let routeBeingRestored = null;

const scrollAreas = () => [...document.querySelectorAll('.scroll-area')];

export const rememberScrollPosition = (route) => {
    if (!route) {
        return;
    }

    positionPerRoute.delete(route);
    positionPerRoute.set(route, {
        window: window.scrollY,
        scrollAreas: scrollAreas().map(node => node.scrollTop),
    });

    if (positionPerRoute.size > MAX_REMEMBERED_ROUTES) {
        positionPerRoute.delete(positionPerRoute.keys().next().value);
    }
};

export const beginScrollRestore = (route, restore) => {
    routeBeingRestored = restore && positionPerRoute.has(route) ? route : null;

    return positionPerRoute.get(routeBeingRestored)?.window ?? 0;
};

export const restoreScrollArea = (node) => {
    if (!routeBeingRestored) {
        return;
    }

    node.scrollTop = positionPerRoute.get(routeBeingRestored).scrollAreas[scrollAreas().indexOf(node)] ?? 0;
};
