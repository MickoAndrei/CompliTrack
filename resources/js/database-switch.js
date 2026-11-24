export async function waitForDatabaseSwitch(statusUrl, {
    get = (url, options) => window.axios.get(url, options),
    delay = milliseconds => new Promise(resolve => setTimeout(resolve, milliseconds)),
    onProgress = () => {},
    onUnavailable = () => {},
    signal,
    attempts = 300,
    timeoutMs = 300000,
    now = () => Date.now(),
} = {}) {
    const deadline = now() + timeoutMs;
    for (let attempt = 0; attempt < attempts && !signal?.aborted && now() < deadline; attempt++) {
        try {
            const { data } = await get(statusUrl, { timeout: 5000, signal });
            onProgress(data);
            if (data.complete) return data;
        } catch (error) {
            if (signal?.aborted) return null;
            const status = error.response?.status;
            // The web server and its tunnel can be unavailable during restart.
            if (status && ![408, 429, 502, 503, 504].includes(status)) throw error;
            onUnavailable();
        }
        await delay(2000);
    }
    if (signal?.aborted) return null;
    throw new Error('The switch could not be verified within five minutes. Refresh after services reconnect to check its status.');
}

export async function reconnectDatabaseSwitch({
    get = (url, options) => window.axios.get(url, options),
    delay = milliseconds => new Promise(resolve => setTimeout(resolve, milliseconds)),
    signal,
    attempts = 60,
} = {}) {
    for (let attempt = 0; attempt < attempts && !signal?.aborted; attempt++) {
        try {
            const { data } = await get('/api/v1/settings', { timeout: 5000, signal });
            return { state: data, statusUrl: data.databaseSwitch?.pendingRequest?.statusUrl };
        } catch (error) {
            if (signal?.aborted) return null;
            const status = error.response?.status;
            if (status === 401) return { signedOut: true };
            if (status && ![408, 429, 502, 503, 504].includes(status)) throw error;
        }
        await delay(2000);
    }
    if (signal?.aborted) return null;
    throw new Error('Application services have not reconnected yet.');
}
