import {FilterManager} from "../data-table/filter-manager";
import {serialize} from "../data-table/filter-url";
import {bindFilterControls} from "../data-table/filter-controls";
import {updateQueryString} from "../../core/history";

export default class PhotoWall {
    constructor(wrapper) {
        this.wrapper = wrapper;
        this.resetBtn = wrapper.querySelector('[data-dataTable-reset]');
        this.filterManager = new FilterManager(wrapper);
        this.allImages = Array.from(this.wrapper.querySelectorAll('[data-image]')).map(el => ({
            element: el,
            filterables: JSON.parse(el.getAttribute('data-filterables')),
            active: true
        }));
    }

    render() {
        const redraw = (historyMode) => {
            const activeFilters = this.filterManager.getActiveFilters();
            this.filterManager.updateDropdownState(activeFilters);
            if (historyMode) {
                updateQueryString(serialize({filters: this.filterManager.toUrlFilters()}), historyMode);
            }

            const images = this.filterManager.applyFiltersToRows(this.allImages);
            for (const {element, active} of images) {
                element.classList.toggle('hidden', !active);
            }

            this.resetBtn?.classList.toggle('hidden', !(Object.keys(activeFilters).length > 0));

            const resultCount = this.wrapper.querySelector('[data-dataTable-result-count]');
            if (resultCount) resultCount.innerText = images.filter((image) => image.active).length;
        };

        bindFilterControls({
            wrapper: this.wrapper,
            filterManager: this.filterManager,
            onChange: redraw,
        });

        redraw(null);
    }
}
