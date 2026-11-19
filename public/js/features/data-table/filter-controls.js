import {parse} from "./filter-url";
import {HistoryMode} from "../../core/history";

export const bindFilterControls = ({wrapper, filterManager, onChange, onUrlState = () => {}, onReset = () => {}}) => {
    const applyUrlState = () => {
        const state = parse(new URLSearchParams(location.search));
        filterManager.resetAll();
        filterManager.prefillFromUrl(state.filters);
        onUrlState(state);
    };

    applyUrlState();

    wrapper.querySelectorAll('[data-dataTable-filter]').forEach(el => {
        if (!el.matches('[data-dataTable-filter*="[]"]')) {
            el.addEventListener('input', () => onChange(HistoryMode.PUSH));
            return;
        }

        el.addEventListener('input', () => onChange(null));
        el.addEventListener('change', () => onChange(HistoryMode.PUSH));
    });

    wrapper.querySelector('[data-dataTable-reset]')?.addEventListener('click', e => {
        e.preventDefault();
        onReset();
        filterManager.resetAll();
        onChange(HistoryMode.PUSH);
    });

    wrapper.querySelectorAll('[data-datatable-filter-clear]').forEach(btn => {
        btn.addEventListener('click', e => {
            e.preventDefault();
            filterManager.resetOne(btn.getAttribute('data-datatable-filter-clear'));
            onChange(HistoryMode.PUSH);
        });
    });

    wrapper.addEventListener('click', (e) => {
        const preset = e.target.closest('[data-date-preset]');
        if (!preset) return;
        e.preventDefault();
        const filterName = preset.closest('[data-date-preset-filter]').getAttribute('data-date-preset-filter');
        filterManager.applyDatePreset(preset.getAttribute('data-date-preset'), filterName);
        onChange(HistoryMode.PUSH);
    });

    window.addEventListener('popstate', () => {
        applyUrlState();
        onChange(null);
    });
};
