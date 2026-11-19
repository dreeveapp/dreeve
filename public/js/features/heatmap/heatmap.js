import {FilterManager} from "../data-table/filter-manager";
import {serialize} from "../data-table/filter-url";
import {bindFilterControls} from "../data-table/filter-controls";
import HeatmapDrawer from "./heatmap-drawer";
import {fetchJson} from "../../utils";
import {updateQueryString} from "../../core/history";

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

            this.resetBtn?.classList.toggle('hidden', !(Object.keys(activeFilters).length > 0));
            const resultCount = this.wrapper.querySelector('[data-dataTable-result-count]');
            if (resultCount) resultCount.innerText = routes.filter((route) => route.active).length;
        };

        bindFilterControls({
            wrapper: this.wrapper,
            filterManager: this.filterManager,
            onChange: redraw,
        });

        redraw(null);
    };

}
