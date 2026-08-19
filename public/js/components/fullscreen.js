export default function initFullscreen(rootNode) {
    rootNode.querySelectorAll('[data-fullscreen-content]:not([data-fullscreen-content-bound])').forEach((content) => {
        content.setAttribute('data-fullscreen-content-bound', '');
        content.addEventListener('fullscreenchange', () => {
            content.toggleAttribute('data-fullscreen-enabled', Boolean(document.fullscreenElement));
        });
    });

    rootNode.querySelectorAll('[data-fullscreen-trigger]:not([data-fullscreen-trigger-bound])').forEach((el) => {
        el.setAttribute('data-fullscreen-trigger-bound', '');
        el.addEventListener('click', (e) => {
            e.preventDefault();

            if (document.fullscreenElement) {
                return;
            }

            el.closest('[data-fullscreen-content]')?.requestFullscreen();
        });
    });
}
