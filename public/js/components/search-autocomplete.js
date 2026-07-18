import autoComplete from "../../libraries/autocomplete";
import {fetchJson} from "../utils";

const initSearchAutocomplete = (input) => {
    const searchUrl = input.getAttribute('data-autocomplete-url');
    if (!searchUrl) {
        return;
    }

    const autoCompleteJS = new autoComplete({
        selector: () => input,
        data: {
            src: async (query) => {
                try {
                    return await fetchJson(`${searchUrl}${searchUrl.includes('?') ? '&' : '?'}q=${encodeURIComponent(query)}`);
                } catch {
                    return [];
                }
            },
            keys: ['label'],
        },
        threshold: 2,
        debounce: 250,
        searchEngine: (query, record) => record,
        resultsList: {
            maxResults: 10,
            tabSelect: true,
        },
        resultItem: {
            element: (item, data) => {
                item.textContent = '';
                const label = document.createElement('div');
                label.textContent = data.value.label;
                item.append(label);

                if (data.value.sublabel) {
                    const sublabel = document.createElement('div');
                    sublabel.className = 'text-xs text-gray-500';
                    sublabel.textContent = data.value.sublabel;
                    item.append(sublabel);
                }
            },
        },
    });

    autoCompleteJS.input.addEventListener('selection', (event) => {
        input.value = event.detail.selection.value.value;
    });
};

export default function initSearchAutocompletes(rootNode = document) {
    rootNode.querySelectorAll('input[data-autocomplete-url]').forEach(initSearchAutocomplete);
}
