const BASE_OPTIONS = {
    handle: '[data-drag-handle]',
    animation: 150,
    easing: 'cubic-bezier(0.2, 0, 0, 1)',
    forceFallback: true,
    fallbackOnBody: true,
    fallbackTolerance: 4,

    ghostClass: 'drag-sort-ghost',
    chosenClass: 'drag-sort-chosen',
    dragClass: 'drag-sort-dragging',

    scroll: true,
    scrollSensitivity: 40,
    scrollSpeed: 12,
    bubbleScroll: true,
    delay: 0,
    delayOnTouchOnly: true,
    touchStartThreshold: 4,

    swapThreshold: 0.65,
};

const initialised = new WeakSet();

const itemsOf = (container, itemSelector) =>
    [...container.children].filter((child) => child.matches(itemSelector));

export default async function createDragSort(container, {itemSelector, onReorder, ...overrides} = {}) {
    if (!container || initialised.has(container)) {
        return null;
    }
    initialised.add(container);

    const {default: Sortable} = await import(/* webpackChunkName: "sortable" */ 'sortablejs');

    let orderBeforeDrag = [];

    return new Sortable(container, {
        ...BASE_OPTIONS,
        draggable: itemSelector,
        ...overrides,
        onStart: () => {
            orderBeforeDrag = itemsOf(container, itemSelector);
        },
        onEnd: () => {
            const items = itemsOf(container, itemSelector);
            const unchanged = items.length === orderBeforeDrag.length
                && items.every((item, index) => item === orderBeforeDrag[index]);

            if (unchanged) {
                return;
            }

            onReorder?.(items);
        },
    });
}
