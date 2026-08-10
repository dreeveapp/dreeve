import {fetchJson} from "../utils";

const POLL_INTERVAL_MS = 30000;

export default function initImportStatus() {
    const badge = document.getElementById('import-pending-badge');
    if (!badge) {
        return;
    }

    const url = document.querySelector('meta[name="import-status-url"]')?.getAttribute('content');
    if (!url) {
        return;
    }

    let intervalId = null;

    const poll = async () => {
        try {
            const {pending} = await fetchJson(url);
            if (!pending) {
                badge.remove();

                if (intervalId !== null) {
                    clearInterval(intervalId);
                    intervalId = null;
                }
            }
        } catch {

        }
    };

    intervalId = setInterval(poll, POLL_INTERVAL_MS);
}
