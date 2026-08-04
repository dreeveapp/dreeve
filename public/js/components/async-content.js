export default function initAsyncContent(rootNode) {
    rootNode.querySelectorAll('[data-async-content-url]:not([data-async-content-loaded])').forEach(async (node) => {
        node.setAttribute('data-async-content-loaded', '');

        try {
            const response = await fetch(node.getAttribute('data-async-content-url'), {cache: 'no-store'});
            if (!response.ok) {
                return;
            }

            node.innerHTML = await response.text();
        } catch (error) {
            console.error('Failed to load async content:', error);
        }
    });
}
