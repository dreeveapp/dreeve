import {FilterName} from "../data-table/storage";
import {FilterManager} from "../data-table/filter-manager";
import HeatmapDrawer from "./heatmap-drawer";
import {fetchJson} from "../../utils";

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

        const redraw = (updateStorage = true) => {
            const activeFilters = this.filterManager.getActiveFilters();
            this.filterManager.updateDropdownState(activeFilters);
            if(updateStorage){
                this.filterManager.updateStorage(FilterName.HEATMAP, activeFilters);
            }

            const routes = this.filterManager.applyFiltersToRows(allRoutes);
            this.drawer.redraw(routes);

            this.resetBtn.classList.toggle('hidden', !(Object.keys(activeFilters).length > 0));
            const resultCount = this.wrapper.querySelector('[data-dataTable-result-count]');
            if (resultCount) resultCount.innerText = routes.filter((route) => route.active).length;
        };

        this.filterManager.prefillFromStorage(FilterName.HEATMAP);
        redraw(false);

        this.wrapper.querySelectorAll('[data-dataTable-filter]').forEach(el => el.addEventListener('input', redraw));

        if (this.resetBtn) {
            this.resetBtn.addEventListener('click', e => {
                e.preventDefault();
                this.filterManager.resetAll(FilterName.HEATMAP);
                redraw();
            });
        }

        this.wrapper.querySelectorAll('[data-datatable-filter-clear]').forEach(btn => {
            btn.addEventListener('click', e => {
                e.preventDefault();
                const name = btn.getAttribute('data-datatable-filter-clear');
                this.filterManager.resetOne(name);
                redraw();
            });
        });

        this.wrapper.addEventListener('click', (e) => {
            const preset = e.target.closest('[data-date-preset]');
            if (!preset) return;
            e.preventDefault();
            const filterName = preset.closest('[data-date-preset-filter]').getAttribute('data-date-preset-filter');
            this.filterManager.applyDatePreset(preset.getAttribute('data-date-preset'), filterName);
            redraw();
        });
    };

}
