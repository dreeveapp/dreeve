const registries = new Map();

const registryFor = (Component) => {
    if (!registries.has(Component)) {
        registries.set(Component, new Map());
    }

    return registries.get(Component);
};

export const getComponent = (root, Component) => registryFor(Component).get(root);

export const initComponents = (rootNode, selector, Component) => {
    const instances = registryFor(Component);

    instances.forEach((instance, root) => {
        if (document.contains(root)) {
            return;
        }
        instance.destroy?.();
        instances.delete(root);
    });

    rootNode.querySelectorAll(selector).forEach((root) => {
        if (instances.has(root)) {
            return;
        }
        const instance = new Component(root);
        instances.set(root, instance);
        instance.init();
    });
};
