export default function initFullscreen(rootNode) {
    rootNode.querySelectorAll('[data-fullscreen-trigger]').forEach((el) => {
        el.addEventListener('click', (e) => {
            e.preventDefault();

            if (document.fullscreenElement) {
                return;
            }

            const fullScreenContent = el.closest('[data-fullscreen-content]');
            fullScreenContent.requestFullscreen();

            fullScreenContent.addEventListener('fullscreenchange', () => {
                fullScreenContent.toggleAttribute('data-fullscreen-enabled', Boolean(document.fullscreenElement))
            });

        });
    });
}
