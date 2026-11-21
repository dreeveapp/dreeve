/**
 * Adds client-side sorting to server-rendered tables.
 *
 * Tables managed by the dataTable system (wrapped in a [data-dataTable-settings] node) are ignored,
 * they already handle sorting themselves. Any other table with sortable header cells
 * (th[data-dataTable-sort]) sorts its own rows in place. The value to sort on is read from the
 * matching cell (same column index) through its [data-sort-value] attribute. Cells without a value
 * are pushed to the bottom.
 */
export default function initLocalTableSorters(rootNode) {
    rootNode.querySelectorAll('table').forEach((table) => {
        if (table.closest('[data-dataTable-settings]')) return;

        const tbody = table.querySelector('tbody');
        const columns = [...table.querySelectorAll('thead th[data-dataTable-sort]')];
        if (!tbody || columns.length === 0) return;

        let sortOn = null;
        let sortAsc = false;

        const valueFor = (row, columnIndex) => {
            const cell = row.children[columnIndex];
            if (!cell || cell.dataset.sortValue === undefined) return undefined;

            return parseFloat(cell.dataset.sortValue);
        };

        const apply = () => {
            columns.forEach(column => column.querySelector('.sorting-icon')?.setAttribute('aria-sort', 'none'));

            const column = columns.find(th => th.getAttribute('data-dataTable-sort') === sortOn);
            if (!column) return;

            column.querySelector('.sorting-icon')?.setAttribute('aria-sort', sortAsc ? 'ascending' : 'descending');

            const columnIndex = [...column.parentNode.children].indexOf(column);
            const rows = [...tbody.querySelectorAll('tr')];

            rows.sort((a, b) => {
                const aVal = valueFor(a, columnIndex);
                const bVal = valueFor(b, columnIndex);
                if (aVal === undefined || Number.isNaN(aVal)) return 1;
                if (bVal === undefined || Number.isNaN(bVal)) return -1;
                if (aVal < bVal) return sortAsc ? -1 : 1;
                if (aVal > bVal) return sortAsc ? 1 : -1;

                return 0;
            });

            rows.forEach(row => tbody.appendChild(row));
        };

        columns.forEach(column => column.addEventListener('click', () => {
            const columnKey = column.getAttribute('data-dataTable-sort');
            sortAsc = columnKey === sortOn ? !sortAsc : true;
            sortOn = columnKey;

            apply();
        }));
    });
}
