import {basePath, fetchJson} from "../../utils";
import {resolveEchartsCallbacks} from "./echarts-callbacks";
import {v5Theme, v5DarkTheme} from "./echarts-themes";
import {serialize} from "../data-table/filter-url";
import {router} from "../../core/router";

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

        echarts.registerTheme('v5', v5Theme());
        echarts.registerTheme('v5-dark', v5DarkTheme());
    }

    init(rootNode, isDarkMode) {
        this.theme = isDarkMode ? 'v5-dark' : 'v5';
        const handlers = this.getClickHandlers();
        rootNode.querySelectorAll('[data-echarts-options], [data-echarts-options-url]').forEach(chartNode => {
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
            if (clickHandlerName && handlers[clickHandlerName]) {
                chart.on('click', function (params) {
                    const clickData = JSON.parse(chartNode.getAttribute('data-echarts-click-data') || '{}');
                    handlers[clickHandlerName](params, clickData);
                });
            }

            this.allCharts.push(chart);
            this.resizeObserver.observe(chartNode);
        });
    }

    getClickHandlers() {
        return {
            handleMonthlyStatsClick: (params, clickData) => {
                if (!params || !params.dataIndex || !params.seriesName) {
                    return;
                }
                const month = (params.dataIndex + 1).toString().padStart(2, "0");

                router.navigateTo(`${basePath()}/monthly-stats/${params.seriesName}-${month}`);
            },
            handleWeeklyStatsClick: (params, clickData) => {
                if (!params || !params.dataIndex) {
                    return;
                }

                const weeks = clickData.weeks;
                if (!params.dataIndex in weeks) {
                    return;
                }
                const filters = serialize({filters: {
                    "sportType": clickData.sportTypes,
                    "start-date": {"from": weeks[params.dataIndex]['from'], "to": weeks[params.dataIndex]['to']},
                }});

                router.navigateTo(`${basePath()}/activities?${filters}`);
            },
            handleActivityGridChartClick: (params, clickData) => {
                if (!params || !params.value || params.value < 1) {
                    return;
                }

                const filters = serialize({filters: {
                    "start-date": {"from": params.value[0], "to": params.value[0]},
                }});

                router.navigateTo(`${basePath()}/activities?${filters}`);
            },
        };
    }

    reset() {
        this.resizeObserver.disconnect();
        // Dropping the references is not enough: echarts keeps its own registry.
        this.allCharts.forEach(chart => {
            if (!chart.isDisposed()) {
                chart.dispose();
            }
        });
        this.allCharts = [];
        this.pendingTheme.clear();
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
