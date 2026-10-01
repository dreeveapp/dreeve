import {resolveEchartsCallbacks} from "./echarts-callbacks";
import {loadEcharts} from "./echarts-loader";
import {HistoryMode, updateQueryString} from "../../core/history";

const PRIMARY_COLOR = '#5470c6';
const SECONDARY_COLOR = '#fac858';
const IMPROVING_COLOR = '#3ba272';
const DECLINING_COLOR = '#ee6666';
const NEUTRAL_COLOR = '#9ca3af';

const escapeHtml = (value) => String(value).replace(/[&<>"']/g, char => `&#${char.charCodeAt(0)};`);

const trendOf = (points) => {
    if (points.length < 3) {
        return null;
    }

    const n = points.length;
    const meanX = points.reduce((total, [x]) => total + x, 0) / n;
    const meanY = points.reduce((total, [, y]) => total + y, 0) / n;

    let covariance = 0;
    let variance = 0;
    points.forEach(([x, y]) => {
        covariance += (x - meanX) * (y - meanY);
        variance += (x - meanX) ** 2;
    });

    if (0 === variance) {
        return null;
    }

    const slope = covariance / variance;
    const intercept = meanY - slope * meanX;
    const first = points[0][0];
    const last = points[n - 1][0];

    return {slope, from: [first, slope * first + intercept], to: [last, slope * last + intercept]};
};

const trendColor = (direction, slope) => {
    if ('neutral' === direction || 0 === slope) {
        return NEUTRAL_COLOR;
    }

    const improving = 'lowerIsBetter' === direction ? slope < 0 : slope > 0;

    return improving ? IMPROVING_COLOR : DECLINING_COLOR;
};

export default class MetricExplorer {
    constructor(rootNode) {
        this.rootNode = rootNode.querySelector('[data-metric-explorer]');
        this.selected = {primary: null, secondary: null};
    }

    async init() {
        if (!this.rootNode) return;

        this.dataset = JSON.parse(this.rootNode.getAttribute('data-metric-explorer-dataset'));
        this.chartNode = this.rootNode.querySelector('[data-metric-explorer-chart]');
        if (!this.chartNode || !this.dataset) return;

        const params = new URLSearchParams(window.location.search);
        this.selected.primary = this.resolveMetric(params.get('primary'), this.dataset.defaults.primary);
        this.selected.secondary = this.resolveMetric(params.get('secondary'), this.dataset.defaults.secondary);

        Object.keys(this.selected).forEach(axis => {
            const select = this.rootNode.querySelector(`[data-metric-explorer-select="${axis}"]`);
            if (!select) return;

            this.dataset.metrics.forEach(metric => {
                const option = document.createElement('option');
                option.value = metric.key;
                option.textContent = metric.unit ? `${metric.label} (${metric.unit})` : metric.label;
                option.disabled = !metric.available;
                option.selected = metric.key === this.selected[axis];
                select.append(option);
            });

            select.addEventListener('change', () => {
                this.selected[axis] = select.value;
                this.syncUrl();
                this.render();
            });
        });

        await loadEcharts();
        this.render();
    }

    resolveMetric(requested, fallback) {
        const isAvailable = key => this.dataset.metrics.some(metric => metric.key === key && metric.available);

        if (isAvailable(requested)) return requested;
        if (isAvailable(fallback)) return fallback;

        return this.dataset.metrics.find(metric => metric.available)?.key ?? null;
    }

    metric(key) {
        return this.dataset.metrics.find(metric => metric.key === key) ?? null;
    }

    syncUrl() {
        const params = new URLSearchParams(window.location.search);
        Object.entries(this.selected).forEach(([axis, key]) => key ? params.set(axis, key) : params.delete(axis));

        updateQueryString(params.toString(), HistoryMode.REPLACE);
    }

    seriesFor(metric, axisIndex, color) {
        if (!metric) return [];

        const points = this.dataset.rows
            .filter(row => null !== row.values[metric.key] && undefined !== row.values[metric.key])
            .map(row => ({value: [new Date(row.date).getTime(), row.values[metric.key]], label: row.label}));

        if (!points.length) return [];

        const series = [{
            name: metric.label,
            type: 'line',
            yAxisIndex: axisIndex,
            showSymbol: true,
            symbolSize: 6,
            data: points,
            lineStyle: {width: 2, type: 0 === axisIndex ? 'solid' : 'dashed'},
            itemStyle: {color},
        }];

        if ('neutral' !== metric.direction) {
            const best = points.reduce((a, b) => (
                'lowerIsBetter' === metric.direction ? (b.value[1] < a.value[1] ? b : a) : (b.value[1] > a.value[1] ? b : a)
            ));
            series[0].markPoint = {
                symbolSize: 44,
                itemStyle: {color},
                label: {formatter: '★', color: '#fff'},
                data: [{coord: best.value, name: metric.label}],
            };
        }

        const trend = trendOf(points.map(point => point.value));
        if (trend) {
            series.push({
                name: `${metric.label} trend`,
                type: 'line',
                yAxisIndex: axisIndex,
                silent: true,
                showSymbol: false,
                data: [trend.from, trend.to],
                lineStyle: {width: 2, type: 'dotted', color: trendColor(metric.direction, trend.slope)},
                tooltip: {show: false},
            });
        }

        return series;
    }

    axisFor(metric, position) {
        return {
            type: 'value',
            position,
            scale: true,
            name: metric ? (metric.unit ? `${metric.label} (${metric.unit})` : metric.label) : '',
            nameLocation: 'end',
            nameTextStyle: {align: 'right' === position ? 'right' : 'left'},
            splitLine: {show: 'left' === position},
            axisLabel: metric?.formatter ? {formatter: metric.formatter} : {},
        };
    }

    render() {
        const chart = echarts.getInstanceByDom(this.chartNode);
        if (!chart) return;

        const primary = this.metric(this.selected.primary);
        const secondary = this.metric(this.selected.secondary);
        const bestValues = {};
        [primary, secondary].filter(Boolean).forEach(metric => {
            const values = this.dataset.rows
                .map(row => row.values[metric.key])
                .filter(value => null !== value && undefined !== value);
            if (!values.length) return;
            bestValues[metric.key] = 'lowerIsBetter' === metric.direction ? Math.min(...values) : Math.max(...values);
        });

        const option = {
            animation: false,
            grid: {top: '40px', left: '10px', right: '10px', bottom: '30px', containLabel: true},
            tooltip: {
                trigger: 'axis',
                axisPointer: {type: 'cross'},
                formatter: (params) => {
                    const entries = (Array.isArray(params) ? params : [params]).filter(p => p.data?.label);
                    if (!entries.length) return '';

                    const date = echarts.time.format(entries[0].data.value[0], '{dd}-{MM}-{yyyy}', false);
                    const lines = entries.map(entry => {
                        const metric = this.dataset.metrics.find(m => m.label === entry.seriesName);
                        const value = entry.data.value[1];
                        const best = metric ? bestValues[metric.key] : undefined;
                        const delta = undefined !== best && best !== value
                            ? ` <span style="color:${NEUTRAL_COLOR}">(${value > best ? '+' : ''}${Math.round((value - best) * 100) / 100})</span>`
                            : '';

                        return `${entry.marker} ${escapeHtml(entry.seriesName)}: <b>${value}</b>${metric?.unit ? ' ' + escapeHtml(metric.unit) : ''}${delta}`;
                    });

                    return `<b>${escapeHtml(entries[0].data.label)}</b><br/>${date}<br/>${lines.join('<br/>')}`;
                },
            },
            legend: {show: true, top: '0px'},
            xAxis: {type: 'time'},
            yAxis: [this.axisFor(primary, 'left'), this.axisFor(secondary, 'right')],
            series: [
                ...this.seriesFor(primary, 0, PRIMARY_COLOR),
                ...this.seriesFor(secondary, 1, SECONDARY_COLOR),
            ],
        };

        resolveEchartsCallbacks(option);
        chart.setOption(option, {notMerge: true});
    }
}
