import {HiddenColumnStorage} from "./storage";

export class ColumnManager {
    constructor(wrapper, tableName) {
        this.wrapper = wrapper;
        this.tableName = tableName;
        this.table = wrapper.querySelector('table');
        this.container = wrapper.querySelector('[data-dataTable-columns]');
        this.scopeClass = `data-table-columns-${tableName.replace(/[^a-zA-Z0-9_-]/g, '-')}`;
        this.columns = [];
        this.hiddenColumnKeys = new Set();
        this.styleElement = null;
    }

    init() {
        this.columns = this._resolveColumns();
        if (!this.container || this.columns.length === 0) return;

        const columnKeys = this.columns.map(column => column.key);
        this.hiddenColumnKeys = new Set(
            HiddenColumnStorage.get(this.tableName).filter(key => columnKeys.includes(key))
        );

        this.table.classList.add(this.scopeClass);
        this.styleElement = document.createElement('style');
        this.wrapper.appendChild(this.styleElement);

        this.columns.forEach(column => this.container.appendChild(this._renderToggle(column)));

        this.container.addEventListener('change', e => {
            const toggle = e.target.closest('[data-dataTable-column-toggle]');
            if (!toggle) return;

            const key = toggle.getAttribute('data-dataTable-column-toggle');
            if (toggle.checked) {
                this.hiddenColumnKeys.delete(key);
            } else {
                this.hiddenColumnKeys.add(key);
            }
            this._apply();
        });

        this.wrapper.querySelectorAll('[data-dataTable-columns-reset]').forEach(resetBtn => {
            resetBtn.addEventListener('click', e => {
                e.preventDefault();
                this.hiddenColumnKeys.clear();
                this.container.querySelectorAll('[data-dataTable-column-toggle]').forEach(toggle => toggle.checked = true);
                this._apply();
            });
        });

        this._apply();
    }

    _resolveColumns() {
        const columns = new Map();

        this.table?.querySelectorAll(':scope > thead > tr').forEach(row => {
            let columnIndex = 0;

            [...row.cells].forEach(cell => {
                const key = cell.getAttribute('data-dataTable-column');
                const span = cell.colSpan || 1;

                if (key) {
                    const column = columns.get(key) ?? {key: key, label: this._resolveLabel(cell), indexes: []};
                    column.indexes.push(...Array.from({length: span}, (_, offset) => columnIndex + offset));
                    columns.set(key, column);
                }
                columnIndex += span;
            });
        });

        return [...columns.values()];
    }

    _resolveLabel(cell) {
        const label = cell.getAttribute('title')
            ?? cell.querySelector('[title]')?.getAttribute('title')
            ?? cell.textContent;

        return label.replace(/\s+/g, ' ').trim();
    }

    _renderToggle(column) {
        const id = `${this.scopeClass}-${column.key}`;

        const toggle = document.createElement('input');
        toggle.type = 'checkbox';
        toggle.id = id;
        toggle.className = 'peer';
        toggle.checked = !this.hiddenColumnKeys.has(column.key);
        toggle.setAttribute('data-dataTable-column-toggle', column.key);

        const label = document.createElement('label');
        label.setAttribute('for', id);
        label.textContent = column.label;

        const wrapper = document.createElement('div');
        wrapper.append(toggle, label);

        const listItem = document.createElement('li');
        listItem.appendChild(wrapper);

        return listItem;
    }

    _apply() {
        const hiddenIndexes = new Set(
            this.columns.filter(column => this.hiddenColumnKeys.has(column.key)).flatMap(column => column.indexes)
        );

        // Rows holding a single cell are placeholders (loading, no results) that span the whole table, leave them be.
        const selectors = [...hiddenIndexes].map(
            index => `${this._scope()} > tbody > tr:has(> *:nth-child(2)) > *:nth-child(${index + 1})`
        );
        ['thead', 'tfoot'].forEach(section => {
            this.table.querySelectorAll(`:scope > ${section} > tr`).forEach((row, rowIndex) => {
                let columnIndex = 0;

                [...row.cells].forEach((cell, cellIndex) => {
                    const span = cell.colSpan || 1;
                    const isHidden = Array.from({length: span}, (_, offset) => columnIndex + offset)
                        .every(index => hiddenIndexes.has(index));

                    if (isHidden) {
                        selectors.push(`${this._scope()} > ${section} > tr:nth-child(${rowIndex + 1}) > *:nth-child(${cellIndex + 1})`);
                    }
                    columnIndex += span;
                });
            });
        });

        this.styleElement.textContent = selectors.length > 0 ? `${selectors.join(',')}{display:none}` : '';

        this.container.closest('.filter-dropdown')
            ?.querySelector('[data-dropdown]')
            ?.classList.toggle('active', this.hiddenColumnKeys.size > 0);

        HiddenColumnStorage.set(this.tableName, [...this.hiddenColumnKeys]);
    }

    _scope() {
        return `table.${this.scopeClass}`;
    }
}
