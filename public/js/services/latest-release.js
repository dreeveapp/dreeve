import {basePath, fetchJson} from "../utils";

const ACKNOWLEDGED_RELEASE_STORAGE_KEY = 'acknowledgedReleaseVersion';

export const fetchAvailableUpdate = async () => {
    const {latestVersion, updateAvailable} = await fetchJson(`${basePath()}/api/internal/latest-release?v=${window.dreeve.appVersion}`);

    return updateAvailable ? latestVersion : null;
};

export const showLatestVersion = (latestVersion) => {
    const $latestVersionEl = document.querySelector('[data-latest-version]');
    if (!$latestVersionEl) return;

    const $link = $latestVersionEl.querySelector('a');
    if ($link) {
        $link.href = $link.href.replace('[LATEST_VERSION]', latestVersion);
        $link.textContent = $link.textContent.replace('[LATEST_VERSION]', latestVersion);
    }
    $latestVersionEl.classList.remove('hidden');
};

export const showReleaseAnnouncement = async (latestVersion) => {
    if (localStorage.getItem(ACKNOWLEDGED_RELEASE_STORAGE_KEY) === latestVersion) return;

    const response = await fetch(`${basePath()}/api/internal/release-announcement`);
    if (!response.ok) return;

    const html = (await response.text()).trim();
    if (!html) return;

    const $wrapper = document.createElement('div');
    $wrapper.innerHTML = html.replaceAll('[LATEST_VERSION]', latestVersion);
    const $dialog = $wrapper.querySelector('dialog[data-release-announcement]');
    if (!$dialog) return;

    $dialog.addEventListener('close', () => {
        localStorage.setItem(ACKNOWLEDGED_RELEASE_STORAGE_KEY, latestVersion);
        $dialog.remove();
    });
    $dialog.addEventListener('click', (event) => {
        if (event.target === $dialog) $dialog.close();
    });

    document.body.appendChild($dialog);
    $dialog.showModal();
};
