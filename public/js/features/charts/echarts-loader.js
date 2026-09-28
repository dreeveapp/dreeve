import {loadScript} from "../../utils";
import {v5Theme, v5DarkTheme} from "./echarts-themes";

let loaded = null;

export const loadEcharts = () => loaded ??= window.dreeve.echartsUrls
    .reduce((previous, url) => previous.then(() => loadScript(url)), Promise.resolve())
    .then(() => {
        echarts.registerTheme('v5', v5Theme());
        echarts.registerTheme('v5-dark', v5DarkTheme());
    });
