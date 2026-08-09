import {fetchJson} from "../../utils";
import {resolveEchartsCallbacks} from "./echarts-callbacks";
import {v5Theme, v5DarkTheme} from "./echarts-themes";
import {FilterStorage, FilterName} from "../data-table/storage";

export default class ChartManager {
    constructor(router, modalManager) {
        this.router = router;
        this.modalManager = modalManager;
        this.allCharts = [];
        this.resizeObserver = new ResizeObserver(entries => {
            for (const entry of entries) {
                const chart = echarts.getInstanceByDom(entry.target);
                if (chart && entry.target.offsetParent) {
                    chart.resize();
                }
            }
        });

        echarts.registerTheme('v5', v5Theme());
        echarts.registerTheme('v5-dark', v5DarkTheme());
    }

    init(rootNode, isDarkMode) {
        const handlers = this.getClickHandlers();
        const connectedCharts = [];
        rootNode.querySelectorAll('[data-echarts-options], [data-echarts-options-url]').forEach(chartNode => {
            const chart = echarts.init(chartNode, isDarkMode ? 'v5-dark' : 'v5');
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
            if (chartNode.hasAttribute('data-echarts-connect')) {
                connectedCharts.push(chart);
            }

            this.allCharts.push(chart);
            this.resizeObserver.observe(chartNode);
        });
        echarts.connect(connectedCharts);
    }

    getClickHandlers() {
        return {
            handleMonthlyStatsClick: (params, clickData) => {
                if (!params || !params.dataIndex || !params.seriesName) {
                    return;
                }
                const month = (params.dataIndex + 1).toString().padStart(2, "0");
                const basePath = window.dreeve?.appUrl?.basePath || '';
                const prefix = basePath ? '/' + basePath.replace(/^\/+|\/+$/g, '') : '';
                const modalId = `${prefix}/api/page/month/${params.seriesName}-${month}`;

                this.modalManager.open(modalId);
                this.router.pushCurrentRouteToHistoryState(modalId);
            },
            handleWeeklyStatsClick: (params, clickData) => {
                if (!params || !params.dataIndex) {
                    return;
                }

                const weeks = clickData.weeks;
                if (!params.dataIndex in weeks) {
                    return;
                }
                FilterStorage.set(FilterName.ACTIVITIES, {
                    "sportType": clickData.sportTypes,
                    "start-date": {"from": weeks[params.dataIndex]['from'], "to": weeks[params.dataIndex]['to']},
                });

                this.router.navigateTo(`/activities`);
            },
            handleActivityGridChartClick: (params, clickData) => {
                if (!params || !params.value || params.value < 1) {
                    return;
                }

                FilterStorage.set(FilterName.ACTIVITIES, {
                    "start-date": {"from": params.value[0], "to": params.value[0]},
                });

                this.router.navigateTo(`/activities`);
            },
        };
    }

    reset() {
        this.resizeObserver.disconnect();
        this.allCharts = [];
    }

    toggleDarkTheme(isDarkMode) {
        this.allCharts
            .filter(chart => chart.getDom().offsetParent)
            .forEach(chart => chart.setTheme(isDarkMode ? 'v5-dark' : 'v5'));
    }
}
