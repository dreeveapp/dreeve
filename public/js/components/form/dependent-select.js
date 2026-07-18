const snapshots = new WeakMap();

const placeholderOf = (options) => options.find((option) => '' === option.value) || null;

const rebuild = (select, placeholder, options) => {
    select.innerHTML = '';
    if (placeholder) {
        const option = new Option(placeholder.text, '');
        option.disabled = true;
        option.selected = true;
        select.add(option);
    }
    options.forEach((option) => select.add(new Option(option.text, option.value)));
};

const sync = (select, controllerValue) => {
    const options = snapshots.get(select);
    const placeholder = placeholderOf(options);
    const matches = options.filter((option) => '' !== option.value && option.when === controllerValue);

    if (select.hasAttribute('data-collapse-single')) {
        const field = select.closest('[data-field]') || select;

        if (1 === matches.length) {
            rebuild(select, null, matches);
            select.value = matches[0].value;
            select.disabled = false;
            field.classList.add('hidden');
            return;
        }

        if ('' === controllerValue || 0 === matches.length) {
            rebuild(select, placeholder, []);
            select.disabled = true;
            field.classList.add('hidden');
            return;
        }

        rebuild(select, placeholder, matches);
        select.disabled = false;
        field.classList.remove('hidden');
        return;
    }

    rebuild(select, placeholder, matches);
    select.disabled = '' === controllerValue || 0 === matches.length;
};

export default function initDependentSelects(rootNode = document) {
    const controllers = new Map();

    rootNode.querySelectorAll('select[data-depends-on]').forEach((select) => {
        const controller = document.getElementById(select.getAttribute('data-depends-on'));
        if (!controller) {
            return;
        }

        snapshots.set(select, Array.from(select.options).map((option) => ({
            value: option.value,
            text: option.text,
            when: option.dataset.when ?? null,
        })));

        if (!controllers.has(controller)) {
            controllers.set(controller, []);
        }
        controllers.get(controller).push(select);
    });

    controllers.forEach((dependents, controller) => {
        const update = () => dependents.forEach((select) => sync(select, controller.value));
        controller.addEventListener('change', update);
        update();
    });
}
