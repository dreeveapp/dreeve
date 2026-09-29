import {ClusterRenderer} from "./cluster-renderer";
import {ColumnManager} from "./column-manager";
import {FilterManager} from "./filter-manager";
import {Sorter} from "./sorter";
import {parse, serialize} from "./filter-url";
import {debounce} from "../../utils";
import {HistoryMode, updateQueryString} from "../../core/history";

export default function initDataTables(rootNode) {
    rootNode.querySelectorAll('div[data-dataTable-settings]').forEach((wrapper) => {
        const table = wrapper.querySelector('table');
        const tbody = table?.querySelector('tbody');
        const scrollElem = wrapper.querySelector('.scroll-area');
        const searchInput = wrapper.querySelector('input[type="search"]');
        const resetBtn = wrapper.querySelector('[data-dataTable-reset]');
        const settings = JSON.parse(wrapper.getAttribute('data-dataTable-settings'));

        if (!table || !tbody || !searchInput) return;

        const filterManager = new FilterManager(wrapper);
        const clusterRenderer = new ClusterRenderer(wrapper, tbody, scrollElem);
        const sorter = new Sorter(wrapper.querySelectorAll('thead th[data-dataTable-sort]'));

        if (settings.toggleableColumns) {
            new ColumnManager(wrapper, settings.name).init();
        }

        const applyUrlState = () => {
            const state = parse(new URLSearchParams(location.search));
            filterManager.resetAll();
            filterManager.prefillFromUrl(state.filters);
            searchInput.value = state.search;
            sorter.sortOn = state.sortOn;
            sorter.sortAsc = state.sortAsc;
        };

        applyUrlState();

        fetch(settings.url, {cache: 'no-store'}).then(async (response) => {
            const unsortedRows = await response.json();
            const dataRows = sorter.apply([...unsortedRows]);

            // Init cluster.
            clusterRenderer.init(dataRows);

            const updateState = (historyMode, resetScroll = true) => {
                const search = searchInput.value.trim();
                const activeFilters = filterManager.getActiveFilters();

                filterManager.updateDropdownState(activeFilters);
                if (historyMode) {
                    const queryString = serialize({
                        filters: filterManager.toUrlFilters(),
                        search: search,
                        sortOn: sorter.sortOn,
                        sortAsc: sorter.sortAsc,
                    });
                    updateQueryString(queryString, historyMode);
                }
                const rows = filterManager.applyFiltersToRows(dataRows, search);
                clusterRenderer.update(rows, resetScroll);
                resetBtn.classList.toggle('hidden', !(Object.keys(activeFilters).length > 0 || search.length > 0));
            };

            updateState(null, false);

            // Attach events.
            searchInput.addEventListener('input', debounce(() => updateState(HistoryMode.REPLACE)));
            wrapper.querySelectorAll('[data-dataTable-filter]').forEach(el => {
                if (!el.matches('[data-dataTable-filter*="[]"]')) {
                    el.addEventListener('input', () => updateState(HistoryMode.PUSH));
                    return;
                }

                el.addEventListener('input', () => updateState(null));
                el.addEventListener('change', () => updateState(HistoryMode.PUSH));
            });
            sorter.attachListeners(dataRows, () => updateState(HistoryMode.PUSH));

            if (resetBtn) {
                resetBtn.addEventListener('click', e => {
                    e.preventDefault();
                    searchInput.value = '';
                    filterManager.resetAll();
                    updateState(HistoryMode.PUSH);
                });
            }

            wrapper.querySelectorAll('[data-datatable-filter-clear]').forEach(btn => {
                btn.addEventListener('click', e => {
                    e.preventDefault();
                    const name = btn.getAttribute('data-datatable-filter-clear');
                    filterManager.resetOne(name);
                    updateState(HistoryMode.PUSH);
                });
            });

            wrapper.addEventListener('click', (e) => {
                const preset = e.target.closest('[data-date-preset]');
                if (!preset) return;
                e.preventDefault();
                const filterName = preset.closest('[data-date-preset-filter]').getAttribute('data-date-preset-filter');
                filterManager.applyDatePreset(preset.getAttribute('data-date-preset'), filterName);
                updateState(HistoryMode.PUSH);
            });

            window.addEventListener('popstate', () => {
                applyUrlState();
                sorter.apply(Object.assign(dataRows, unsortedRows));
                updateState(null);
            });
        });
    });
}
