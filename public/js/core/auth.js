import {fetchJson} from "../utils";

export const initAuthState = async () => {
    try {
        const {isAuthenticated} = await fetchJson(window.dreeve.authStatusUrl);
        if (!isAuthenticated) {
            return;
        }
        document.documentElement.setAttribute('data-authenticated', '');
    } catch (e) {
        // Stay anonymous, the routes we link to are protected server side anyway.
    }
}
