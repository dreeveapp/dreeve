import {basePath, fetchJson} from "../utils";

export const updateLatestRelease = async () => {
    const $latestVersionEl = document.querySelector('[data-latest-version]');
    if (!$latestVersionEl) return;

    try {
        const {latestVersion, updateAvailable} = await fetchJson(`${basePath()}/api/internal/latest-release`);
        if (!updateAvailable) return;

        const $link = $latestVersionEl.querySelector('a');
        if ($link) {
            $link.href = $link.href.replace('[LATEST_VERSION]', latestVersion);
            $link.textContent = $link.textContent.replace('[LATEST_VERSION]', latestVersion);
        }
        $latestVersionEl.classList.remove('hidden');
    } catch (err) {
        console.error('Error checking latest release:', err);
    }
}
