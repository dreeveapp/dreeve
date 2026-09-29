export const HistoryMode = Object.freeze({
    PUSH:       'push',
    REPLACE:    'replace',
});

export const updateQueryString = (queryString, mode) => {
    const url = queryString ? `${location.pathname}?${queryString}` : location.pathname;

    if (mode === HistoryMode.REPLACE) {
        window.history.replaceState(window.history.state, '', url);
        return;
    }

    if (url === location.pathname + location.search) return;
    window.history.pushState(null, '', url);
}
