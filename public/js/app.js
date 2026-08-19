import "./core/public-path";
import {eventBus, Events} from "./core/event-bus";
import {router} from "./core/router";
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
import initAsyncContent, {abortPendingAsyncContent} from "./components/async-content";
import ScrollTo from "./components/scroll-to";
import MilestoneFilter from "./features/milestones/milestone-filter";
import DarkModeManager from "./components/dark-mode";
import initDropdowns from "./components/dropdown";
import {initAccordions, initPopovers, initDrawers} from "flowbite";

// Boot router.
router.boot();

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

eventBus.on(Events.PAGE_LOADED, async ({page}) => {
    abortPendingAsyncContent();
    chartManager.reset();
    initElements(document);

    if (page === 'milestones') {
        new MilestoneFilter(document).init();
    }
    if (page === 'heatmap') {
        const $heatmapWrapper = document.querySelector('.heatmap-wrapper');
        const {default: Heatmap} = await import(
            /* webpackChunkName: "leaflet" */ './features/heatmap/heatmap'
        );
        await new Heatmap($heatmapWrapper).render();
    }
    if (page === 'photos') {
        const $photoWallWrapper = document.querySelector('.photo-wall-wrapper');
        new PhotoWall($photoWallWrapper).render();
    }
    if (page === 'chat') {
        const {default: Chat} = await import(
            /* webpackChunkName: "chat" */ './features/chat/chat'
        );
        new Chat(document).render();
    }
});
eventBus.on(Events.ASYNC_CONTENT_LOADED, ({node}) => {
    initElements(node);
});
(async () => {
    await updateGithubLatestRelease();
})();
