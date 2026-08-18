import "./core/public-path";
import {eventBus, Events} from "./core/event-bus";
import {FilterStorage} from "./features/data-table/storage";
import Router from "./core/router";
import {updateGithubLatestRelease} from "./services/github";
import initSidebar from "./components/sidebar";
import ChartManager from "./features/charts/chart-manager";
import {registerEchartsCallbacks} from "./features/charts/echarts-callbacks";
import ModalManager from "./components/modals";
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

const $main = document.querySelector("main");

// Boot router.
const router = new Router($main);
router.boot();

registerEchartsCallbacks();
initDrawers();

const modalManager = new ModalManager(router);
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
modalManager.init();
darkModeManager.attachEventListeners();

eventBus.on(Events.DARK_MODE_TOGGLED, ({darkModeEnabled}) => {
    chartManager.toggleDarkTheme(darkModeEnabled);
});

eventBus.on(Events.PAGE_LOADED, async ({page, modalId}) => {
    modalManager.close();

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

    if (modalId) {
        modalManager.open(modalId);
    }
});
eventBus.on(Events.MODAL_HISTORY_CHANGED, ({modalId}) => {
    modalManager.close();
    if (modalId) {
        modalManager.open(modalId);
    }
});
eventBus.on(Events.NAVIGATION_REQUESTED, ({route}) => {
    router.navigateTo(route, null);
});
eventBus.on(Events.NAVIGATION_CLICKED, ({link}) => {
    if (!link || !link.hasAttribute('data-filters')) {
        return;
    }
    const filters = JSON.parse(link.getAttribute('data-filters'));
    Object.entries(filters).forEach(([tableName, tableFilters]) => {
        FilterStorage.set(tableName, tableFilters);
    });
});
eventBus.on(Events.ASYNC_CONTENT_LOADED, ({node}) => {
    initElements(node);
});
eventBus.on(Events.MODAL_LOADED, async ({node, modalName}) => {
    initElements(node);

    if (modalName === 'ai-chat') {
        const {default: Chat} = await import(
            /* webpackChunkName: "chat" */ './features/chat/chat'
            );
        new Chat(node).render();
    }
});
const $modalAIChat = document.querySelector('a[data-modal-custom-ai]');
if ($modalAIChat) {
    $modalAIChat.addEventListener('click', (e) => {
        e.preventDefault();
        e.stopPropagation();
        modalManager.openAndPushToHistoryState($modalAIChat.getAttribute('data-modal-custom-ai'));
    });
}

(async () => {
    await updateGithubLatestRelease();
})();
