import {FilterManager} from "../data-table/filter-manager";
import {parse, serialize} from "../data-table/filter-url";
import {router} from "../../core/router";

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
        const redraw = (syncUrl = true) => {
            const activeFilters = this.filterManager.getActiveFilters();
            this.filterManager.updateDropdownState(activeFilters);
            if (syncUrl) {
                router.replaceQuery(serialize({filters: this.filterManager.toUrlFilters()}));
            }

            const images = this.filterManager.applyFiltersToRows(this.allImages);
            for (const {element, active} of images) {
                element.classList.toggle('hidden', !active);
            }

            this.resetBtn.classList.toggle('hidden', !(Object.keys(activeFilters).length > 0));

            const resultCount = this.wrapper.querySelector('[data-dataTable-result-count]');
            if (resultCount) resultCount.innerText = images.filter((image) => image.active).length;
        };

        this.filterManager.prefillFromUrl(parse(new URLSearchParams(location.search)).filters);
        redraw(false);

        this.wrapper.querySelectorAll('[data-dataTable-filter]').forEach(el => el.addEventListener('input', redraw));

        if (this.resetBtn) {
            this.resetBtn.addEventListener('click', e => {
                e.preventDefault();
                this.filterManager.resetAll();
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
    }
}
