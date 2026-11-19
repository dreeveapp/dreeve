export const debounce = (func, timeout = 300) => {
    let timer;
    return (...args) => {
        clearTimeout(timer);
        timer = setTimeout(() => {
            func(...args);
        }, timeout);
    };
}

export const basePath = () => {
    const configured = window.dreeve?.appUrl?.basePath || '';

    return configured ? '/' + configured.replace(/^\/+|\/+$/g, '') : '';
}

export const loadScript = (src) => new Promise((resolve, reject) => {
    const script = document.createElement('script');
    script.src = src;
    script.onload = resolve;
    script.onerror = () => reject(new Error(`Failed to load ${src}`));
    document.head.appendChild(script);
});

export const fetchJson = async (url) => {
    const response = await fetch(url);

    if (!response.ok) {
        throw new Error(`Failed to fetch ${url}: ${response.status}`);
    }

    return response.json();
}

export const dispatchCommand = async (commandName, payload = {}) => {
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
    if (!csrfToken) {
        throw new Error('csrf-token expected to be found');
    }
    const url = document.querySelector('meta[name="dispatch-command-url"]')?.getAttribute('content');
    if (!url) {
        throw new Error('dispatch-command-url expected to be found');
    }

    const response = await fetch(url, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken,
        },
        body: JSON.stringify({commandName, payload}),
    });

    if (!response.ok) {
        const error = await response.json().catch(() => ({}));
        throw new Error(error.error ?? `Failed to dispatch ${commandName}: ${response.status}`);
    }
}

