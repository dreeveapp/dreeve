const FlyToPlacesControl = L.Control.extend({
    options: {
        position: 'topright',
        places: {}
    },
    onAdd: function (map) {
        const container = L.DomUtil.create('ul', 'leaflet-control leaflet-control--custom no-dark');
        this.options.places.forEach((place) => {
            const countryCode = place.countryCode.toLowerCase();
            const item = L.DomUtil.create('li', '', container);

            item.innerHTML = '<img src="assets/images/flags/' + countryCode + '.svg" width="20" title="' + window.dreeve.countries[countryCode.toUpperCase()] + '" />'
            // Prevent click events propagation to map.
            L.DomEvent.disableClickPropagation(item);
            L.DomEvent.on(item, 'click', function () {
                map.flyToBounds(place.bounds, {duration: 3});
            });
        });

        // Prevent right click event propagation to map.
        L.DomEvent.on(container, 'contextmenu', function (ev) {
            L.DomEvent.stopPropagation(ev);
        });

        // Prevent scroll events propagation to map when cursor on the div.
        L.DomEvent.disableScrollPropagation(container);

        return container;
    },
    onRemove: function () {

    }
});

export function createFlyToPlacesControl(options = {}) {
    return new FlyToPlacesControl(options);
}

const MapToolsControl = L.Control.extend({
    options: {
        position: 'topleft',
        bounds: null,
        padding: [24, 24],
        showFullscreen: true,
        showReset: true,
        toggles: [],
    },
    onAdd: function (map) {
        const container = L.DomUtil.create('div', 'leaflet-bar leaflet-control--custom no-dark');
        const mapEl = map.getContainer();
        const options = this.options;

        const enterIcon = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M8 3H5a2 2 0 0 0-2 2v3"/><path d="M21 8V5a2 2 0 0 0-2-2h-3"/><path d="M3 16v3a2 2 0 0 0 2 2h3"/><path d="M16 21h3a2 2 0 0 0 2-2v-3"/></svg>';
        const exitIcon = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M8 3v3a2 2 0 0 1-2 2H3"/><path d="M21 8h-3a2 2 0 0 1-2-2V3"/><path d="M3 16h3a2 2 0 0 1 2 2v3"/><path d="M16 21v-3a2 2 0 0 1 2-2h3"/></svg>';
        const resetIcon = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="2" y1="12" x2="5" y2="12"/><line x1="19" y1="12" x2="22" y2="12"/><line x1="12" y1="2" x2="12" y2="5"/><line x1="12" y1="19" x2="12" y2="22"/><circle cx="12" cy="12" r="7"/><circle cx="12" cy="12" r="3"/></svg>';

        const makeButton = (icon, title) => {
            const button = L.DomUtil.create('a', '', container);
            button.href = '#';
            button.title = title;
            button.setAttribute('role', 'button');
            button.setAttribute('aria-label', title);
            button.innerHTML = icon;
            return button;
        };

        if (options.showFullscreen) {
            const fsButton = makeButton(enterIcon, 'Toggle fullscreen');
            L.DomEvent.on(fsButton, 'click', function (e) {
                L.DomEvent.preventDefault(e);
                if (!document.fullscreenElement) {
                    mapEl.requestFullscreen().catch(function () {});
                } else {
                    document.exitFullscreen();
                }
            });

            this._onFullscreenChange = function () {
                fsButton.innerHTML = document.fullscreenElement === mapEl ? exitIcon : enterIcon;
                setTimeout(function () { map.invalidateSize(); }, 100);
            };
            document.addEventListener('fullscreenchange', this._onFullscreenChange);
        }

        if (options.showReset) {
            const resetButton = makeButton(resetIcon, 'Reset view');
            L.DomEvent.on(resetButton, 'click', function (e) {
                L.DomEvent.preventDefault(e);
                if (options.bounds) {
                    map.fitBounds(options.bounds, { padding: options.padding });
                }
            });
        }

        options.toggles.forEach(function (toggle) {
            const button = makeButton(toggle.icon, toggle.title);
            button.setAttribute('aria-pressed', 'false');
            let isActive = false;
            let isBusy = false;

            L.DomEvent.on(button, 'click', function (e) {
                L.DomEvent.preventDefault(e);
                if (isBusy) return;

                isActive = !isActive;
                isBusy = true;
                button.classList.toggle('is-active', isActive);
                button.classList.add('is-busy');
                button.setAttribute('aria-pressed', String(isActive));

                Promise.resolve(toggle.onToggle(isActive))
                    .catch(function () {
                        isActive = false;
                        button.classList.remove('is-active');
                        button.setAttribute('aria-pressed', 'false');
                    })
                    .finally(function () {
                        isBusy = false;
                        button.classList.remove('is-busy');
                    });
            });
        });

        L.DomEvent.disableClickPropagation(container);

        return container;
    },
    onRemove: function () {
        if (this._onFullscreenChange) {
            document.removeEventListener('fullscreenchange', this._onFullscreenChange);
            this._onFullscreenChange = null;
        }
    }
});

export function createMapToolsControl(options = {}) {
    return new MapToolsControl(options);
}