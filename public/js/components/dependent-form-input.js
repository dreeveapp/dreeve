// Shows/hides form fields based on the value of another form field (the "controller").
// Works when the controller is a checkbox or a select.
//
// Markup convention — put both attributes on the element you want toggled (usually a
// field wrapper):
//   data-dependent-on="<id of the controlling input>"
//   data-visible-when="<comma-separated controller values that make it visible>"
//
// The controller's "current value" is resolved as:
//   * checkbox        -> "checked" / "unchecked"
//   * select (or any
//     other input)    -> its current value
//
// When a field is hidden, the form controls inside it are disabled so their values
// aren't submitted (and don't trip HTML validation). Nesting is supported: a control
// stays disabled while any of its dependent ancestors is hidden.
//
// Sibling component: dependent-select.js (which rebuilds a <select>'s options based on
// another select). Kept separate on purpose for now; the two may be merged later.

const SELECTOR = '[data-dependent-on][data-visible-when]';

const controllerValueOf = (controller) => {
    if (controller instanceof HTMLInputElement && 'checkbox' === controller.type) {
        return controller.checked ? 'checked' : 'unchecked';
    }

    return controller.value;
};

const allowedValuesOf = (field) => (field.getAttribute('data-visible-when') ?? '')
    .split(',')
    .map((value) => value.trim())
    .filter((value) => '' !== value);

const isVisible = (field) => {
    const controller = document.getElementById(field.getAttribute('data-dependent-on'));
    if (!controller) {
        return true;
    }

    return allowedValuesOf(field).includes(controllerValueOf(controller));
};

export default function initDependentFormInputs(rootNode = document) {
    const fields = Array.from(rootNode.querySelectorAll(SELECTOR));
    if (0 === fields.length) {
        return;
    }

    const visibility = new WeakMap();

    const apply = () => {
        fields.forEach((field) => visibility.set(field, isVisible(field)));
        fields.forEach((field) => field.classList.toggle('hidden', !visibility.get(field)));

        // Disable controls that live inside a hidden field. Walk up the chain of
        // dependent ancestors so nested fields stay disabled while a parent is hidden.
        rootNode.querySelectorAll('input, select, textarea').forEach((control) => {
            let ancestor = control.closest(SELECTOR);
            if (null === ancestor) {
                return; // not managed by this component, leave it untouched
            }

            let hidden = false;
            while (null !== ancestor) {
                if (false === visibility.get(ancestor)) {
                    hidden = true;
                    break;
                }
                ancestor = ancestor.parentElement ? ancestor.parentElement.closest(SELECTOR) : null;
            }

            control.disabled = hidden;
        });
    };

    const controllers = new Set();
    fields.forEach((field) => {
        const controller = document.getElementById(field.getAttribute('data-dependent-on'));
        if (controller) {
            controllers.add(controller);
        }
    });
    controllers.forEach((controller) => controller.addEventListener('change', apply));

    apply();
}
