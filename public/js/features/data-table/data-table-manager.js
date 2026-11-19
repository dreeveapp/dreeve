import {ClusterRenderer} from "./cluster-renderer";
import {ColumnManager} from "./column-manager";
import {FilterManager} from "./filter-manager";
import {Sorter} from "./sorter";
import {serialize} from "./filter-url";
import {bindFilterControls} from "./filter-controls";
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

        let unsortedRows = null;
        let dataRows = null;

        const updateState = (historyMode, resetScroll = true) => {
            if (null === dataRows) return;

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
            resetBtn?.classList.toggle('hidden', !(Object.keys(activeFilters).length > 0 || search.length > 0));
        };

        bindFilterControls({
            wrapper: wrapper,
            filterManager: filterManager,
            onChange: updateState,
            onUrlState: (state) => {
                searchInput.value = state.search;
                sorter.sortOn = state.sortOn;
                sorter.sortAsc = state.sortAsc;
                if (null !== dataRows) sorter.apply(Object.assign(dataRows, unsortedRows));
            },
            onReset: () => {
                searchInput.value = '';
            },
        });

        fetch(settings.url).then(async (response) => {
            unsortedRows = await response.json();
            dataRows = sorter.apply([...unsortedRows]);

            clusterRenderer.init(dataRows);
            updateState(null, false);

            searchInput.addEventListener('input', debounce(() => updateState(HistoryMode.REPLACE)));
            sorter.attachListeners(dataRows, () => updateState(HistoryMode.PUSH));
        });
    });
}
