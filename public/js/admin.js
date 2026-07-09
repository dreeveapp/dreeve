import initPasswordToggle from "./components/password-toggle";
import initFormLoadingState from "./components/form-loading-state";
import initDispatchCommandForm from "./components/dispatch-command-form";
import initDependentSelects from "./components/dependent-select";
import initDependentFormInputs from "./components/dependent-form-input";
import initDropdowns from "./components/dropdown";
import initRebuildStatus from "./components/rebuild-status";
import initToasts from "./components/toast";
import FileDropzoneUpload from "./features/file-upload/file-dropzone-upload";
import {initImageDropZones} from "./features/file-upload/image-dropzone-upload";
import initDashboardLayout from "./features/dashboard/dashboard-layout";
import initSortableLists from "./components/sortable-list";
import {initDrawers, initCollapses} from "flowbite";

initDrawers();
initCollapses();

initPasswordToggle();
initFormLoadingState();
initDispatchCommandForm();
initDependentSelects();
initDependentFormInputs();
initDropdowns(document);
initRebuildStatus();
initToasts();

new FileDropzoneUpload(document).init();
initImageDropZones(document);
initDashboardLayout(document);
initSortableLists(document);