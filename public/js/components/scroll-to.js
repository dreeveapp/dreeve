export default class ScrollTo {
    constructor() {
        this.pendingObservers = new WeakMap();
        this.boundRoots = new WeakSet();
    }

    init(rootNode) {
        if (this.boundRoots.has(rootNode)) return;
        this.boundRoots.add(rootNode);

        rootNode.addEventListener('click', (e) => {
            const trigger = e.target.closest('[data-scroll-to]');
            if (!trigger) return;

            const target = document.getElementById(trigger.dataset.scrollTo);
            if (!target) return;

            e.preventDefault();
            target.scrollIntoView({behavior: 'smooth', block: 'center'});

            const highlightTarget = target.querySelector('[data-scroll-to-highlight]') || target;
            this.highlightWhenVisible(highlightTarget);
        });
    }

    highlightWhenVisible(element) {
        const previous = this.pendingObservers.get(element);
        if (previous) {
            if (previous.observer) previous.observer.disconnect();
            if (previous.timeoutId) clearTimeout(previous.timeoutId);
        }

        const observer = new IntersectionObserver((entries, obs) => {
            if (!entries[0].isIntersecting) return;
            obs.disconnect();

            const timeoutId = setTimeout(() => {
                element.classList.remove('scroll-to-highlighted');
                void element.offsetWidth;
                element.classList.add('scroll-to-highlighted');
                this.pendingObservers.delete(element);
            }, 300);

            this.pendingObservers.set(element, { observer: null, timeoutId });
        }, { threshold: 0.5 });

        this.pendingObservers.set(element, { observer, timeoutId: null });
        observer.observe(element);
    }
}
