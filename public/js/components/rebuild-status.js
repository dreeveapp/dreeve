import {fetchJson} from "../utils";

const POLL_INTERVAL_MS = 30000;

export default function initRebuildStatus() {
    const badge = document.getElementById('rebuild-pending-badge');
    if (!badge) {
        return;
    }

    const url = document.querySelector('meta[name="rebuild-status-url"]')?.getAttribute('content');
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

    if (document.getElementById('rebuild-pending-badge')) {
        intervalId = setInterval(poll, POLL_INTERVAL_MS);
    }
}
