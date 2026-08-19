import "./core/public-path";
import initPasswordToggle from "./components/form/password-toggle";
import initFormLoadingState from "./components/form/form-loading-state";
import initDispatchCommandForm from "./components/form/dispatch-command-form";
import initCheckboxMultiselects from "./components/form/checkbox-multiselect";
import initComboboxes from "./components/form/combobox";
import initDependentSelects from "./components/form/dependent-select";
import initDependentFormInputs from "./components/form/dependent-form-input";
import initCoordinatePickers from "./components/form/coordinate-picker";
import initDropdowns from "./components/dropdown";
import initImportStatus from "./components/import-status";
import initSearchAutocompletes from "./components/form/search-autocomplete";
import initToasts from "./components/toast";
import FileDropzoneUpload from "./features/file-upload/file-dropzone-upload";
import {initImageDropZones} from "./features/file-upload/image-dropzone-upload";
import initDashboardLayout from "./features/dashboard/dashboard-layout";
import initSortableLists from "./components/sortable-list";
import initAsyncContent from "./components/async-content";
import {eventBus, Events} from "./core/event-bus";
import {initDrawers, initCollapses} from "flowbite";

initDrawers();
initCollapses();

initPasswordToggle();
initFormLoadingState();
initDispatchCommandForm();
initCheckboxMultiselects(document);
initComboboxes(document);
initDependentSelects();
initDependentFormInputs();
initCoordinatePickers(document);
initDropdowns(document);
initImportStatus();
initSearchAutocompletes();
initToasts();

new FileDropzoneUpload(document).init();
initImageDropZones(document);
initDashboardLayout(document);
initSortableLists(document);

// Async fragments arrive after the initial pass, so their components need initialising
// too. Only the node-scoped, re-entrant initialisers belong here.
eventBus.on(Events.ASYNC_CONTENT_LOADED, ({node}) => {
    initCheckboxMultiselects(node);
    initComboboxes(node);
    initCoordinatePickers(node);
    initDropdowns(node);
    initDispatchCommandForm(node);
    initImageDropZones(node);
    initDashboardLayout(node);
    initSortableLists(node);
    // Scans document-wide and is guarded against re-binding, so pass the document.
    initDependentFormInputs(document);
    initAsyncContent(node);
});

initAsyncContent(document);