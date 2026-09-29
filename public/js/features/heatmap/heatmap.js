import {FilterManager} from "../data-table/filter-manager";
import {parse, serialize} from "../data-table/filter-url";
import HeatmapDrawer from "./heatmap-drawer";
import {fetchJson} from "../../utils";
import {HistoryMode, updateQueryString} from "../../core/history";

export default class Heatmap {
    constructor(wrapper) {
        this.wrapper = wrapper;
        this.heatmap = wrapper.querySelector('[data-leaflet-routes]');
        this.resetBtn = wrapper.querySelector('[data-dataTable-reset]');
        this.config = JSON.parse(this.heatmap.getAttribute('data-heatmap-config'));

        this.filterManager = new FilterManager(wrapper);
        this.drawer = new HeatmapDrawer(this.heatmap, this.config);
    }

    async render() {
        const apiUrl = this.heatmap.getAttribute('data-leaflet-routes');
        const allRoutes = await fetchJson(apiUrl);

        const redraw = (historyMode) => {
            const activeFilters = this.filterManager.getActiveFilters();
            this.filterManager.updateDropdownState(activeFilters);
            if (historyMode) {
                updateQueryString(serialize({filters: this.filterManager.toUrlFilters()}), historyMode);
            }

            const routes = this.filterManager.applyFiltersToRows(allRoutes);
            this.drawer.redraw(routes);

            this.resetBtn.classList.toggle('hidden', !(Object.keys(activeFilters).length > 0));
            const resultCount = this.wrapper.querySelector('[data-dataTable-result-count]');
            if (resultCount) resultCount.innerText = routes.filter((route) => route.active).length;
        };

        const applyUrlState = () => {
            this.filterManager.resetAll();
            this.filterManager.prefillFromUrl(parse(new URLSearchParams(location.search)).filters);
        };

        applyUrlState();
        redraw(null);

        this.wrapper.querySelectorAll('[data-dataTable-filter]').forEach(el => {
            if (!el.matches('[data-dataTable-filter*="[]"]')) {
                el.addEventListener('input', () => redraw(HistoryMode.PUSH));
                return;
            }

            el.addEventListener('input', () => redraw(null));
            el.addEventListener('change', () => redraw(HistoryMode.PUSH));
        });

        window.addEventListener('popstate', () => {
            applyUrlState();
            redraw(null);
        });

        if (this.resetBtn) {
            this.resetBtn.addEventListener('click', e => {
                e.preventDefault();
                this.filterManager.resetAll();
                redraw(HistoryMode.PUSH);
            });
        }

        this.wrapper.querySelectorAll('[data-datatable-filter-clear]').forEach(btn => {
            btn.addEventListener('click', e => {
                e.preventDefault();
                const name = btn.getAttribute('data-datatable-filter-clear');
                this.filterManager.resetOne(name);
                redraw(HistoryMode.PUSH);
            });
        });

        this.wrapper.addEventListener('click', (e) => {
            const preset = e.target.closest('[data-date-preset]');
            if (!preset) return;
            e.preventDefault();
            const filterName = preset.closest('[data-date-preset-filter]').getAttribute('data-date-preset-filter');
            this.filterManager.applyDatePreset(preset.getAttribute('data-date-preset'), filterName);
            redraw(HistoryMode.PUSH);
        });
    };

}
