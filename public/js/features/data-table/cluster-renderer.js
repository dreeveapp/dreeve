import Clusterize from '../../../libraries/clusterize/clusterize.min';
import {SummableCalculator} from "./summable-calculator";

export class ClusterRenderer {
    constructor(wrapper, tbody, scrollElem) {
        this.wrapper = wrapper;
        this.tbody = tbody;
        this.scrollElem = scrollElem;
        this.cluster = null;
    }

    init(dataRows) {
        this.cluster = new Clusterize({
            rows: dataRows.filter(r => r.active).map(r => r.markup),
            scrollElem: this.scrollElem,
            contentElem: this.tbody,
            no_data_class: 'clusterize-data-empty',
            no_data_text: 'No data found',
            callbacks: {
                clusterChanged: () => {
                    SummableCalculator.render(this.wrapper, dataRows);

                    const resultCount = this.wrapper.querySelector('[data-dataTable-result-count]');
                    if (resultCount) resultCount.innerText = dataRows.filter(r => r.active).length;
                }
            }
        });
    }

    update(dataRows, resetScroll = true) {
        if (!this.cluster) return;
        this.cluster.update(dataRows.filter(r => r.active).map(r => r.markup));
        if (resetScroll) {
            this.scrollElem.scrollTop = 0;
        }
    }
}
