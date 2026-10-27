import {fetchJson} from "../../utils";
import {resolveEchartsCallbacks} from "./echarts-callbacks";
import {loadEcharts} from "./echarts-loader";
import {echartsClickHandlers} from "./echarts-click-handlers";

export default class ChartManager {
    constructor() {
        this.allCharts = [];
        this.theme = null;
        this.pendingTheme = new Set();
        this.resizeObserver = new ResizeObserver(entries => {
            for (const entry of entries) {
                const chart = echarts.getInstanceByDom(entry.target);
                if (!chart || !entry.target.offsetParent) {
                    continue;
                }
                if (this.pendingTheme.delete(chart)) {
                    chart.setTheme(this.theme);
                }
                chart.resize();
            }
        });
    }

    init(rootNode, isDarkMode) {
        this.theme = isDarkMode ? 'v5-dark' : 'v5';
        const chartNodes = rootNode.querySelectorAll('[data-echarts-options], [data-echarts-options-url]');
        if (0 === chartNodes.length) return;

        loadEcharts().then(() => chartNodes.forEach(chartNode => {
            const chart = echarts.init(chartNode, this.theme);
            const chartOptionsUrl = chartNode.getAttribute('data-echarts-options-url');

            const loadOptions = chartOptionsUrl
                ? fetchJson(chartOptionsUrl)
                : Promise.resolve(JSON.parse(chartNode.getAttribute('data-echarts-options')));
            chart.showLoading();

            loadOptions.then(chartOptions => {
                resolveEchartsCallbacks(chartOptions);
                chart.setOption(chartOptions);
            }).catch(error => {
                console.error('Failed to load chart data:', error);
            }).finally(() => {
                chart.hideLoading();
            });

            const clickHandlerName = chartNode.getAttribute('data-echarts-click');
            if (clickHandlerName && echartsClickHandlers[clickHandlerName]) {
                chart.on('click', function (params) {
                    const clickData = JSON.parse(chartNode.getAttribute('data-echarts-click-data') || '{}');
                    echartsClickHandlers[clickHandlerName](params, clickData);
                });
            }

            this.allCharts.push(chart);
            this.resizeObserver.observe(chartNode);
        }));
    }

    toggleDarkTheme(isDarkMode) {
        this.theme = isDarkMode ? 'v5-dark' : 'v5';

        this.allCharts.forEach(chart => {
            if (!chart.getDom().offsetParent) {
                this.pendingTheme.add(chart);
                return;
            }
            this.pendingTheme.delete(chart);
            chart.setTheme(this.theme);
        });
    }
}
