import {showToast} from "../toast";

export const showError = (box, message) => {
    // Forms rendered inside a table row have nowhere to put an error box, so fall back to a toast.
    if (!box) {
        showToast('error', message);
        return;
    }
    box.textContent = message;
    box.classList.remove('hidden');
};

export const hideError = (box) => {
    if (!box) return;
    box.textContent = '';
    box.classList.add('hidden');
};
