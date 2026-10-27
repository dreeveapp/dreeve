import {basePath} from "../../utils";
import {serialize} from "../data-table/filter-url";

export const echartsClickHandlers = {
    handleMonthlyStatsClick: (params, clickData) => {
        if (params?.dataIndex === undefined || !params.seriesName) {
            return;
        }
        const month = (params.dataIndex + 1).toString().padStart(2, "0");

        window.location.assign(`${basePath()}/monthly-stats/${params.seriesName}-${month}`);
    },
    handleWeeklyStatsClick: (params, clickData) => {
        if (params?.dataIndex === undefined) {
            return;
        }

        const weeks = clickData.weeks;
        if (!(params.dataIndex in weeks)) {
            return;
        }
        const filters = serialize({filters: {
            "sportType": clickData.sportTypes,
            "start-date": {"from": weeks[params.dataIndex]['from'], "to": weeks[params.dataIndex]['to']},
        }});

        window.location.assign(`${basePath()}/activities?${filters}`);
    },
    handleActivityGridChartClick: (params, clickData) => {
        if (!params || !params.value || params.value < 1) {
            return;
        }

        const filters = serialize({filters: {
            "start-date": {"from": params.value[0], "to": params.value[0]},
        }});

        window.location.assign(`${basePath()}/activities?${filters}`);
    },
};
