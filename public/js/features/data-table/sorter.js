export class Sorter {
    constructor(columns) {
        this.columns = columns; // NodeList of <th>
        this.sortAsc = false;
        this.sortOn = null;
    }

    apply(dataRows) {
        const column = [...this.columns].find(th => th.getAttribute('data-dataTable-sort') === this.sortOn);
        if (!column) {
            this.sortOn = null;

            return dataRows;
        }

        this.columns.forEach(c => c.querySelector('.sorting-icon')?.setAttribute('aria-sort', 'none'));
        column.querySelector('.sorting-icon')?.setAttribute('aria-sort', this.sortAsc ? 'ascending' : 'descending');

        dataRows.sort((a, b) => {
            const aVal = a.sort[this.sortOn], bVal = b.sort[this.sortOn];
            if (aVal === undefined) return 1;
            if (bVal === undefined) return -1;
            if (aVal < bVal) return this.sortAsc ? -1 : 1;
            if (aVal > bVal) return this.sortAsc ? 1 : -1;
            return 0;
        });

        return dataRows;
    }

    attachListeners(dataRows, onSorted) {
        this.columns.forEach(th => th.addEventListener('click', () => {
            const sortOn = th.getAttribute('data-dataTable-sort');
            this.sortAsc = sortOn === this.sortOn ? !this.sortAsc : true;
            this.sortOn = sortOn;

            this.apply(dataRows);
            onSorted();
        }));
    }
}
