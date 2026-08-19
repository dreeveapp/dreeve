import {DatePreset} from "./date-preset";

export class FilterManager {
    constructor(wrapper) {
        this.wrapper = wrapper;
    }

    _isRangeFilter(value) {
        return typeof value === 'object' && value !== null && 'from' in value && 'to' in value;
    }

    prefillFromUrl(filters) {
        Object.entries(filters).forEach(([key, value]) => {
            if (this._isRangeFilter(value)) {
                return;
            }

            this.wrapper.querySelectorAll(`input[data-dataTable-filter="${key}"]`).forEach(input => {
                const inputValue = input.value.toLowerCase();

                if (Array.isArray(value)) {
                    // Multiple checkbox values.
                    if (value.map(v => v.toLowerCase()).includes(inputValue)) {
                        input.checked = true;
                    }
                } else if (inputValue === value.toLowerCase()) {
                    input.checked = true;
                }
            });
        });

        // Handle range filters (date and number).
        Object.entries(filters)
            .filter(([_, value]) => this._isRangeFilter(value))
            .forEach(([name, range]) => {
                ['from', 'to'].forEach(bound => {
                    const input = this.wrapper.querySelector(`input[name="${name}[${bound}]"]`);
                    if (input && range[bound] !== null) input.value = range[bound];
                });
            });
    }

    toUrlFilters() {
        const filters = {};

        this.wrapper.querySelectorAll('[data-dataTable-filter]:checked').forEach(el => {
            const key = el.getAttribute('data-dataTable-filter');
            const value = el.value.toLowerCase();

            if (!filters[key]) {
                filters[key] = value;
            } else if (Array.isArray(filters[key])) {
                filters[key].push(value);
            } else {
                filters[key] = [filters[key], value];
            }
        });

        this.wrapper.querySelectorAll('[data-dataTable-filter*="[]"]').forEach(group => {
            const name = group.getAttribute('data-dataTable-filter').replace('[]', '');
            const from = group.querySelector(`input[name="${name}[from]"]`);
            const to = group.querySelector(`input[name="${name}[to]"]`);
            if (!from || !to) return;

            if ('' !== from.value || '' !== to.value) {
                filters[name] = {from: from.value || null, to: to.value || null};
            }
        });

        return filters;
    }

    getActiveFilters() {
        const filters = {};

        this.wrapper.querySelectorAll('[data-dataTable-filter]:checked').forEach(el => {
            const key = el.getAttribute('data-dataTable-filter');
            const value = el.value.toLowerCase();

            if (filters[key]) {
                if (Array.isArray(filters[key])) {
                    filters[key].push(value);
                } else {
                    filters[key] = [filters[key], value];
                }
            } else {
                filters[key] = value;
            }
        });

        this.wrapper.querySelectorAll('[data-dataTable-filter*="[]"]').forEach(group => {
            const name = group.getAttribute('data-dataTable-filter').replace('[]', '');
            const from = group.querySelector(`input[name="${name}[from]"]`);
            const to = group.querySelector(`input[name="${name}[to]"]`);
            if (!from || !to) return;

            if (from.type === 'date') {
                const fromMs = from.valueAsDate ? new Date(from.valueAsNumber).setUTCHours(0, 0, 0) : null;
                const toMs = to.valueAsDate ? new Date(to.valueAsNumber).setUTCHours(23, 59, 59) : null;
                if (fromMs !== null || toMs !== null) {
                    filters[name] = { from: fromMs, to: toMs };
                }
            } else if (from.type === 'number') {
                const fromVal = from.value !== '' ? parseFloat(from.value) : null;
                const toVal = to.value !== '' ? parseFloat(to.value) : null;
                if (fromVal !== null || toVal !== null) {
                    const step = Math.min(
                        parseFloat(from.step) || 1,
                        parseFloat(to.step) || 1
                    );
                    const multiplier = Math.round(1 / step);
                    filters[name] = {
                        from: fromVal !== null ? Math.round(fromVal * multiplier) : null,
                        to: toVal !== null ? Math.round(toVal * multiplier) : null,
                        multiplier: multiplier,
                    };
                }
            }
        });

        return filters;
    }

    updateDropdownState(activeFilters) {
        this.wrapper.querySelectorAll('.filter-dropdown [data-dropdown]').forEach(el => {
            el.classList.remove('active');
        });

        Object.entries(activeFilters).forEach(([key, activeFilter]) => {
            if (this._isRangeFilter(activeFilter)) {
                const dropdown = this.wrapper
                    .querySelector(`[data-datatable-filter="${key}[]"]`)
                    ?.closest('.filter-dropdown');

                const toggle = dropdown?.querySelector('[data-dropdown]');
                if (toggle) toggle.classList.add('active');
                return;
            }

            const dropdown = this.wrapper.querySelector(`[data-dataTable-filter="${key}"]:checked`)?.closest('.filter-dropdown');
            const toggle = dropdown?.querySelector('[data-dropdown]');
            if (toggle) toggle.classList.add('active');
        });
    }

    applyFiltersToRows(rows, search = '') {
        const filters = this.getActiveFilters();
        const searchLower = search.toLowerCase();

        rows.forEach(row => {
            row.active = !row.searchables || row.searchables.toLowerCase().includes(searchLower);

            for (const [key, activeFilter] of Object.entries(filters)) {
                const filterValue = row.filterables[key];

                if (this._isRangeFilter(activeFilter)) {
                    if (activeFilter.from !== null && activeFilter.to !== null) {
                        row.active = row.active && filterValue >= activeFilter.from && filterValue <= activeFilter.to;
                    } else if (activeFilter.from !== null) {
                        row.active = row.active && filterValue >= activeFilter.from;
                    } else if (activeFilter.to !== null) {
                        row.active = row.active && filterValue <= activeFilter.to;
                    }
                    continue;
                }

                // Multi-checkbox filter.
                if (Array.isArray(activeFilter)) {
                    row.active = row.active && activeFilter.includes(filterValue?.toLowerCase());
                    continue;
                }

                // Single radio or text filter.
                row.active = row.active &&
                    (
                        Array.isArray(filterValue)
                            ? filterValue.map(v => v.toLowerCase()).includes(activeFilter)
                            : filterValue?.toLowerCase() === activeFilter
                    );
            }
        });
        return rows;
    }

    resetAll() {
        const elements = this.wrapper.querySelectorAll('[data-dataTable-filter], [data-dataTable-filter*="[]"] input');
        elements.forEach(el => {
            if (el.type === 'radio' || el.type === 'checkbox') {
                el.checked = false;
            } else {
                el.value = '';
            }
        });
    }

    applyDatePreset(presetName, filterName) {
        const range = DatePreset.resolve(presetName);
        if (!range) return;

        const fromInput = this.wrapper.querySelector(`input[name="${filterName}[from]"]`);
        const toInput = this.wrapper.querySelector(`input[name="${filterName}[to]"]`);

        if (fromInput) fromInput.valueAsDate = range.from;
        if (toInput) toInput.valueAsDate = range.to;
    }

    resetOne(name) {
        this.wrapper.querySelectorAll(`[data-dataTable-filter][name^="${name}"], [data-dataTable-filter*="[]"] [name^="${name}"]`).forEach(el => {
            if (el.type === 'radio' || el.type === 'checkbox') {
                el.checked = false;
            } else {
                el.value = '';
            }
        });
    }
}
