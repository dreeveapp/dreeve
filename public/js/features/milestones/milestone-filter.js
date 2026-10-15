import {parse, serialize} from "../data-table/filter-url";
import {HistoryMode, updateQueryString} from "../../core/history";

const ALL = 'all';

export default class MilestoneFilter {
    constructor(rootNode) {
        this.rootNode = rootNode;
        this.milestones = this.rootNode.querySelectorAll('[data-milestone-filter-group]');
        this.filtersContainer = this.rootNode.querySelector('[data-milestone-filters]');
    }

    init() {
        if (!this.filtersContainer) return;

        this.applyUrlState();

        this.filtersContainer.addEventListener('change', (e) => {
            const radio = e.target.closest('input[name="filter-group"]');
            if (!radio) return;

            this.filter(radio.value);
            updateQueryString(serialize({filters: radio.value === ALL ? {} : {group: radio.value}}), HistoryMode.PUSH);
        });

        window.addEventListener('popstate', () => this.applyUrlState());
    }

    applyUrlState() {
        const group = parse(new URLSearchParams(location.search)).filters.group;
        const radio = this.filtersContainer.querySelector(`input[name="filter-group"][value="${CSS.escape(group ?? ALL)}"]`)
            ?? this.filtersContainer.querySelector(`input[name="filter-group"][value="${ALL}"]`);

        radio.checked = true;
        this.filter(radio.value);
    }

    filter(group) {
        this.milestones.forEach((milestone) => {
            const match = group === ALL || milestone.dataset.milestoneFilterGroup === group;
            milestone.style.display = match ? '' : 'none';
        });
    }
}
