const integerFormat = new Intl.NumberFormat('en-US', {maximumFractionDigits: 0});

export class SummableCalculator {
    static calculate(rows) {
        return rows
            .filter(r => r.active)
            .reduce((acc, r) => {
                Object.entries(r.summables).forEach(([k, v]) => acc[k] = (acc[k] || 0) + v);
                return acc;
            }, {});
    }

    static render(wrapper, rows) {
        const sums = this.calculate(rows);
        wrapper.querySelectorAll('[data-dataTable-summable]').forEach(node => {
            const key = node.getAttribute('data-dataTable-summable');
            node.innerHTML = sums[key] !== undefined ? integerFormat.format(sums[key]).replaceAll(',', '\u00A0') : 0;
        });
    }
}
