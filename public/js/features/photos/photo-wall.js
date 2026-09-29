import {FilterManager} from "../data-table/filter-manager";
import {parse, serialize} from "../data-table/filter-url";
import {HistoryMode, updateQueryString} from "../../core/history";

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

            this.resetBtn.classList.toggle('hidden', !(Object.keys(activeFilters).length > 0));

            const resultCount = this.wrapper.querySelector('[data-dataTable-result-count]');
            if (resultCount) resultCount.innerText = images.filter((image) => image.active).length;
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
    }
}
