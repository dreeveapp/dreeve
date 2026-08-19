const FILTERS_PREFIX = 'filters';
const RANGE_KEYS = ['from', 'to'];

// filters[sportType][]=ride  |  filters[gear]=gear-1  |  filters[distance][from]=10.5
const FILTER_PARAM = /^filters\[([^\]]+)\](?:\[([^\]]*)\])?$/;

const isEmpty = value => value === null || value === undefined || value === '';

export const parse = (searchParams) => {
    const filters = {};

    for (const [key, value] of searchParams.entries()) {
        const match = FILTER_PARAM.exec(key);
        if (!match || isEmpty(value)) continue;

        const [, name, subKey] = match;

        if (RANGE_KEYS.includes(subKey)) {
            filters[name] = {from: null, to: null, ...filters[name], [subKey]: value};
            continue;
        }

        if (subKey === undefined) {
            filters[name] = value;
            continue;
        }

        // Both "[]" and the "[0]" form http_build_query would produce denote a list member.
        filters[name] = [...(Array.isArray(filters[name]) ? filters[name] : []), value];
    }

    return {
        filters: filters,
        search: searchParams.get('q') ?? '',
        sortOn: searchParams.get('sort'),
        sortAsc: searchParams.get('order') !== 'desc',
    };
};

export const serialize = ({filters = {}, search = '', sortOn = null, sortAsc = true}) => {
    const params = new URLSearchParams();

    Object.entries(filters).forEach(([name, value]) => {
        if (Array.isArray(value)) {
            value.filter(v => !isEmpty(v)).forEach(v => params.append(`${FILTERS_PREFIX}[${name}][]`, v));
            return;
        }

        if (value !== null && typeof value === 'object') {
            RANGE_KEYS.filter(key => !isEmpty(value[key]))
                .forEach(key => params.set(`${FILTERS_PREFIX}[${name}][${key}]`, value[key]));
            return;
        }

        if (!isEmpty(value)) params.set(`${FILTERS_PREFIX}[${name}]`, value);
    });

    if (search) params.set('q', search);
    if (sortOn) {
        params.set('sort', sortOn);
        params.set('order', sortAsc ? 'asc' : 'desc');
    }

    return params.toString().replaceAll('%5B', '[').replaceAll('%5D', ']');
};
