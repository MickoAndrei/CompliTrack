let pending = 0;
let initialized = false;
let settled = false;
let quietTimer;
const jobs = new Set();

export function markPerformance(name) {
    globalThis.performance?.mark?.(`complitrack:${name}`);
}

function checkReady() {
    clearTimeout(quietTimer);
    if (!initialized || pending || settled) return;
    // Let Alpine's initial async effects enqueue their requests before declaring readiness.
    quietTimer = setTimeout(() => {
        if (pending || settled) return;
        settled = true;
        markPerformance('primary-ready');
        for (const job of [...jobs]) job();
    }, 32);
}

export function beginPrimaryRequest() {
    pending++;
    clearTimeout(quietTimer);
    let finished = false;
    return () => {
        if (finished) return;
        finished = true;
        pending--;
        checkReady();
    };
}

export function pageInitialized() {
    initialized = true;
    markPerformance('alpine-initialized');
    checkReady();
}

export function deferAfterPrimaryReady(callback, { maxDelay = 5000 } = {}) {
    let cancelled = false;
    let timer;
    const run = () => {
        jobs.delete(run);
        clearTimeout(timer);
        // Yield so the initial records/shell can paint before a secondary request starts.
        const schedule = globalThis.requestAnimationFrame || (fn => setTimeout(fn, 0));
        schedule(() => { if (!cancelled) callback(); });
    };
    if (settled) run();
    else { jobs.add(run); timer = setTimeout(run, maxDelay); }
    return () => { cancelled = true; clearTimeout(timer); jobs.delete(run); };
}

export function installRequestTiming(axios) {
    axios.interceptors.request.use(config => {
        // Initial notification and health polling must not hold the primary-content gate open.
        if (!/\/notifications(?:[/?]|$)|\/health(?:[/?]|$)/.test(config.url || '')) {
            config.finishPrimaryWork = beginPrimaryRequest();
        }
        return config;
    });
    axios.interceptors.response.use(response => {
        response.config.finishPrimaryWork?.();
        return response;
    }, error => {
        error.config?.finishPrimaryWork?.();
        return Promise.reject(error);
    });
}
