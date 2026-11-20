import { eventBus, Events } from "../core/event-bus";

export const Theme = Object.freeze({
    LIGHT: 'light',
    SYSTEM: 'system',
    DARK: 'dark',
});

const CYCLE = [Theme.LIGHT, Theme.SYSTEM, Theme.DARK, Theme.SYSTEM];

export const isDarkModeEnabled = () => document.documentElement.getAttribute('data-theme-resolved') === 'dark';

export default class DarkModeManager {
    constructor() {
        this.storageKey = 'theme';
        this.$toggleButton = document.querySelector('.dark-mode-toggle');
        this.$themeElement = document.documentElement;
        this.resolvedAttributeName = 'data-theme-resolved'; // one of ['light', 'dark']
        this.selectedAttributeName = 'data-theme-selected'; // one of ['light', 'dark', 'system']
        this.systemPrefersDark = window.matchMedia('(prefers-color-scheme: dark)');

        const selectedTheme = this.getSelectedTheme();
        switch (selectedTheme) {
            // When starting on "system", head towards whichever theme differs from the current one
            // so the first click is visible: the first "system" in the cycle leads to dark, the last one leads to light.
            case Theme.SYSTEM:
                this.position = CYCLE.indexOf(this.systemPrefersDark.matches ? Theme.DARK : Theme.LIGHT) + 1;
                break;
            default:
                this.position = CYCLE.indexOf(selectedTheme);
        }
    }

    attachEventListeners() {
        this.$toggleButton.setAttribute(this.selectedAttributeName, this.getSelectedTheme());
        this.$toggleButton.addEventListener('click', () => {
            this.position = (this.position + 1) % CYCLE.length;
            this.setSelectedTheme(CYCLE[this.position]);
        });
        this.systemPrefersDark.addEventListener('change', () => {
            if (this.getSelectedTheme() === Theme.SYSTEM) {
                this.applyTheme();
            }
        });
    }

    getSelectedTheme() {
        const theme = localStorage.getItem(this.storageKey);
        return Object.values(Theme).includes(theme) ? theme : Theme.SYSTEM;
    }

    setSelectedTheme(theme) {
        localStorage.setItem(this.storageKey, theme);
        this.$toggleButton.setAttribute(this.selectedAttributeName, theme);
        this.applyTheme();
    }

    applyTheme() {
        const selectedTheme = this.getSelectedTheme();
        const resolvedTheme = selectedTheme === Theme.DARK || (selectedTheme === Theme.SYSTEM && this.systemPrefersDark.matches)
            ? 'dark'
            : 'light';
        if (this.$themeElement.getAttribute(this.resolvedAttributeName) === resolvedTheme) {
            return;
        }

        this.$themeElement.setAttribute(this.resolvedAttributeName, resolvedTheme);
        eventBus.emit(Events.DARK_MODE_TOGGLED, { darkModeEnabled: resolvedTheme === 'dark' });
    }
}
