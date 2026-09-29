import {eventBus, Events} from "../core/event-bus";

const MAX_CONCURRENT_REQUESTS = 3;

const queue = [];
let inFlight = 0;

function drain() {
    while (inFlight < MAX_CONCURRENT_REQUESTS && queue.length > 0) {
        const task = queue.shift();
        inFlight++;
        task().finally(() => {
            inFlight--;
            drain();
        });
    }
}

export default function initAsyncContent(rootNode) {
    rootNode.querySelectorAll('[data-async-content-url]:not([data-async-content-loaded])').forEach(node => {
        node.setAttribute('data-async-content-loaded', '');

        queue.push(async () => {
            let html;
            try {
                const response = await fetch(node.getAttribute('data-async-content-url'), {cache: 'no-store'});
                if (!response.ok) {
                    return;
                }

                html = await response.text();
            } catch (error) {
                console.error('Failed to load async content:', error);

                return;
            }

            if (!html.trim()) {
                node.remove();
                return;
            }

            node.innerHTML = html;

            try {
                eventBus.emit(Events.ASYNC_CONTENT_LOADED, {node});
            } catch (error) {
                console.error('Failed to initialise async content:', error);
            }
        });
    });

    drain();
}
