export const resolveEchartsCallbacks = (obj) => {
    if (!obj || typeof obj !== 'object') return;

    for (const key of Object.keys(obj)) {
        const value = obj[key];
        if (value && typeof value === 'object' && typeof value.callback === 'string') {
            const {callback: callbackName, args} = value;
            const callback = window.dreeve.callbacks[callbackName];
            if (!callback) {
                console.error(`ECharts callback "${callbackName}" not registered in window.dreeve.callbacks`);
            } else if (args === undefined) {
                obj[key] = callback;
            } else {
                obj[key] = (...params) => callback(args, ...params);
            }
        } else if (typeof value === 'object' && value !== null) {
            resolveEchartsCallbacks(value);
        }
    }
};

const formatSeconds = (secondsToFormat) => {
    const hours = String(Math.floor(secondsToFormat / 3600)).padStart(2, '0');
    const minutes = String(Math.floor((secondsToFormat % 3600) / 60)).padStart(2, '0');
    const seconds = String(secondsToFormat % 60).padStart(2, '0');

    return `${hours}:${minutes}:${seconds}`;
};

const formatPace = (seconds) => {
    const paceSymbol = window.dreeve.unitSystem.paceSymbol;
    const secondsToFormat = seconds;
    if (secondsToFormat < 60) {
        return `<strong>${secondsToFormat}s</strong>${paceSymbol}`;
    }
    const minutes = Math.floor(secondsToFormat / 60);
    const secs = secondsToFormat % 60;
    return `<strong>${minutes}:${secs.toString().padStart(2, '0')}</strong>${paceSymbol}`;
};

const formatDuration = (seconds) => {
    if (seconds < 60) return seconds + 's';
    if (seconds < 3600) {
        const m = Math.floor(seconds / 60);
        const s = seconds % 60;
        return s > 0 ? m + 'm ' + s + 's' : m + 'm';
    }
    const h = Math.floor(seconds / 3600);
    const m = Math.floor((seconds % 3600) / 60);
    return m > 0 ? h + 'h ' + m + 'm' : h + 'h';
};

export const registerEchartsCallbacks = () => {
    window.dreeve.callbacks = {
        formatSeconds,
        formatPace,
        formatDuration,
        formatSecondsTrimZero: (secondsToFormat) => {
            const time = formatSeconds(secondsToFormat);

            let [hours, minutes, seconds] = time.split(':');
            return String(Number(hours)) === "0" ? `${minutes}:${seconds}` : `${hours}:${minutes}:${seconds}`;
        },
        formatCombinedProfileTooltip: (params) => {
            if (!Array.isArray(params)) params = [params];
            return [...params].sort((a, b) => a.seriesIndex - b.seriesIndex).map(p => {
                if (p.seriesName === '__pace') {
                    return `${p.marker} ${window.dreeve.callbacks.formatPace(p.value)}`;
                }

                const extra = p.data?.extra !== undefined ? ` (${p.data.extra})` : '';
                return `${p.marker} <strong>${p.value}</strong> ${p.seriesName}${extra}`;
            }).join('<br/>');
        },
        formatDataTooltip: (params) => params.data?.tooltip ?? '',
        formatPowerDurationTooltip: (params) => {
            if (!Array.isArray(params)) params = [params];
            const duration = formatDuration(params[0].value[0]);
            return '<b>' + duration + '</b><br/>' + params.map(
                p => p.marker + ' ' + p.seriesName + ': <b>' + p.value[1] + 'w</b>'
                    + (p.data?.relativePower ? ' · ' + p.data.relativePower + ' w/kg' : '')
            ).join('<br/>');
        },
        symbolSize: (params) => {
            return (params[2] / 100) * 15 + 5;
        },
        formatRounded: ({decimals, suffix}, value) => {
            const rounded = Number(value).toFixed(decimals);
            return suffix ? `${rounded} ${suffix}` : rounded;
        },
        toInteger: (value) => {
            return value.toFixed(0);
        },
        formatPercentage: (value) => {
            return `${value.toFixed(0)}%`;
        },
        formatHours: (value) => {
            if (value === undefined || value === null) {
                return '-';
            }
            return `${value.toFixed(0)}h`;
        },
        formatDistance: (value) => {
            if (value === undefined || value === null) {
                return '-';
            }
            const distanceSymbol = window.dreeve.unitSystem.distanceSymbol;
            return `${value.toFixed(0)}${distanceSymbol}`;
        },
        formatElevation: (value) => {
            if (value === undefined || value === null) {
                return '-';
            }
            const elevationSymbol = window.dreeve.unitSystem.elevationSymbol;
            return `${value.toFixed(0)}${elevationSymbol}`;
        },
        formatStackedDistanceTooltip: (params) => {
            if (!Array.isArray(params)) params = [params];
            const distanceSymbol = window.dreeve.unitSystem.distanceSymbol;
            const used = params.filter(p => p.value > 0).sort((a, b) => b.value - a.value);
            const total = used.reduce((sum, p) => sum + p.value, 0);
            return '<b>' + params[0].axisValue + ': ' + total + distanceSymbol + '</b>' + used
                .map(p => '<br/>' + p.marker + ' ' + p.seriesName + ': <b>' + p.value + distanceSymbol + '</b> (' + Math.round(p.value / total * 100) + '%)')
                .join('');
        },
        formatDateOnlyTooltip: (params) => {
            if (!Array.isArray(params)) params = [params];
            const points = params.filter(p => p.data?.value);
            return (points[0]?.data.date ?? '') + points
                .map(p => '<br/>' + p.marker + ' ' + p.seriesName + ': <b>' + p.data.value[1] + '</b>')
                .join('');
        },
    };
};
