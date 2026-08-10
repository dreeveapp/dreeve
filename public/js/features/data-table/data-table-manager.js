import {ClusterRenderer} from "./cluster-renderer";
import {ColumnManager} from "./column-manager";
import {FilterManager} from "./filter-manager";
import {Sorter} from "./sorter";
import {debounce} from "../../utils";

export default function initDataTables(rootNode) {
    rootNode.querySelectorAll('div[data-dataTable-settings]').forEach((wrapper) => {
        const table = wrapper.querySelector('table');
        const tbody = table?.querySelector('tbody');
        const scrollElem = wrapper.querySelector('.scroll-area');
        const searchInput = wrapper.querySelector('input[type="search"]');
        const resetBtn = wrapper.querySelector('[data-dataTable-reset]');
        const settings = JSON.parse(wrapper.getAttribute('data-dataTable-settings'));

        const filterManager = new FilterManager(wrapper);
        const clusterRenderer = new ClusterRenderer(wrapper, tbody, scrollElem);
        const sorter = new Sorter(wrapper.querySelectorAll('thead th[data-dataTable-sort]'));

        if (!table || !tbody || !searchInput) return;

        if (settings.toggleableColumns) {
            new ColumnManager(wrapper, settings.name).init();
        }

        fetch(settings.url, {cache: 'no-store'}).then(async (response) => {
            const dataRows = await response.json();

            // Init cluster.
            clusterRenderer.init(dataRows);

            const updateState = (updateStorage = true) => {
                const search = searchInput.value.trim();
                const activeFilters = filterManager.getActiveFilters();

                filterManager.updateDropdownState(activeFilters);
                if (updateStorage) {
                    filterManager.updateStorage(settings.name, activeFilters);
                }
                const rows = filterManager.applyFiltersToRows(dataRows, search);
                clusterRenderer.update(rows);
                resetBtn.classList.toggle('hidden', !(Object.keys(activeFilters).length > 0 || search.length > 0));
            };

            // Prefill filters.
            filterManager.prefillFromStorage(settings.name);
            updateState(false);

            // Attach events.
            searchInput.addEventListener('input', debounce(updateState));
            wrapper.querySelectorAll('[data-dataTable-filter]').forEach(el => el.addEventListener('input', updateState));
            sorter.attachListeners(dataRows, clusterRenderer.cluster, scrollElem);

            if (resetBtn) {
                resetBtn.addEventListener('click', e => {
                    e.preventDefault();
                    searchInput.value = '';
                    filterManager.resetAll(settings.name);
                    updateState();
                });
            }

            wrapper.querySelectorAll('[data-datatable-filter-clear]').forEach(btn => {
                btn.addEventListener('click', e => {
                    e.preventDefault();
                    const name = btn.getAttribute('data-datatable-filter-clear');
                    filterManager.resetOne(name);
                    updateState();
                });
            });

            wrapper.addEventListener('click', (e) => {
                const preset = e.target.closest('[data-date-preset]');
                if (!preset) return;
                e.preventDefault();
                const filterName = preset.closest('[data-date-preset-filter]').getAttribute('data-date-preset-filter');
                filterManager.applyDatePreset(preset.getAttribute('data-date-preset'), filterName);
                updateState();
            });
        });
    });
}
