import {eventBus, Events} from "../core/event-bus";

export const isDarkModeEnabled = () => document.documentElement.getAttribute('data-theme') === 'dark';

export default class DarkModeManager {
    constructor() {
        this.storageKey = 'theme';
        this.$toggleButton = document.querySelector('.dark-mode-toggle input');
        this.$themeElement = document.documentElement;
        this.dataAttributeName = 'data-theme';
    }

    attachEventListeners() {
        this.$toggleButton.checked = isDarkModeEnabled();
        this.$toggleButton.addEventListener('change', () => {
            this.setDark(!isDarkModeEnabled());
        });
    }

    setDark(enabled) {
        const theme = enabled ? 'dark' : 'light';
        localStorage.setItem(this.storageKey, theme);
        this.$themeElement.setAttribute(this.dataAttributeName, theme);

        this.$toggleButton.checked = enabled;

        eventBus.emit(Events.DARK_MODE_TOGGLED, {darkModeEnabled: enabled});
    }
}
