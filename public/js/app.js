import "./core/public-path";
import {eventBus, Events} from "./core/event-bus";
import {updateGithubLatestRelease} from "./services/github";
import initSidebar from "./components/sidebar";
import ChartManager from "./features/charts/chart-manager";
import {registerEchartsCallbacks} from "./features/charts/echarts-callbacks";
import PhotoWall from "./features/photos/photo-wall";
import initLeafletMaps from "./features/maps/map-manager";
import initTabs from "./components/tabs";
import LazyLoad from "../libraries/lazyload.min";
import initDataTables from "./features/data-table/data-table-manager";
import initFullscreen from "./components/fullscreen";
import initLightGalleries from "./components/light-gallery";
import initAsyncContent from "./components/async-content";
import ScrollTo from "./components/scroll-to";
import MilestoneFilter from "./features/milestones/milestone-filter";
import DarkModeManager from "./components/dark-mode";
import initDropdowns from "./components/dropdown";
import initSearchAutocompletes from "./components/form/search-autocomplete";
import {initAccordions, initPopovers, initDrawers} from "flowbite";

registerEchartsCallbacks();
initDrawers();

const chartManager = new ChartManager();
const scrollTo = new ScrollTo();
const darkModeManager = new DarkModeManager();
const lazyLoad = new LazyLoad({
    thresholds: "50px",
    callback_error: (img) => {
        img.setAttribute("src", window.dreeve.placeholderBrokenImage);
    }
});

const initElements = (rootNode) => {
    lazyLoad.update();

    initTabs(rootNode);
    initDropdowns(rootNode);
    initSearchAutocompletes(rootNode);
    initPopovers();
    initAccordions();

    initDataTables(rootNode);
    chartManager.init(rootNode, darkModeManager.isDarkModeEnabled());
    initLeafletMaps(rootNode);
    initFullscreen(rootNode);
    initLightGalleries(rootNode);
    scrollTo.init(rootNode);
    initAsyncContent(rootNode);
}

initSidebar();
darkModeManager.attachEventListeners();

eventBus.on(Events.DARK_MODE_TOGGLED, ({darkModeEnabled}) => {
    chartManager.toggleDarkTheme(darkModeEnabled);
});

eventBus.on(Events.ASYNC_CONTENT_LOADED, ({node}) => {
    initElements(node);
});

initElements(document);

new MilestoneFilter(document).init();

const $heatmapWrapper = document.querySelector('.heatmap-wrapper');
if ($heatmapWrapper) {
    import(/* webpackChunkName: "leaflet" */ './features/heatmap/heatmap')
        .then(({default: Heatmap}) => new Heatmap($heatmapWrapper).render());
}

const $photoWallWrapper = document.querySelector('.photo-wall-wrapper');
if ($photoWallWrapper) {
    new PhotoWall($photoWallWrapper).render();
}

if (document.querySelector('.chat--wrapper')) {
    import(/* webpackChunkName: "chat" */ './features/chat/chat')
        .then(({default: Chat}) => new Chat(document).render());
}

(async () => {
    await updateGithubLatestRelease();
})();
