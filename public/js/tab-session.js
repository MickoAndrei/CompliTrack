/* Loaded synchronously before account content or application requests. */
(() => {
    const key = 'complitrack.tab';
    const current = new URL(location.href);
    const generate = () => Array.from(crypto.getRandomValues(new Uint8Array(16)), byte => byte.toString(16).padStart(2, '0')).join('');
    let tab;
    try {
        tab = sessionStorage.getItem(key);
        if (!/^[a-f0-9]{32}$/.test(tab || '')) {
            tab = generate();
            sessionStorage.setItem(key, tab);
        }
    } catch {
        // Refuse to silently share credentials if tab storage is unavailable.
        document.documentElement.hidden = true;
        document.addEventListener('DOMContentLoaded', () => {
            document.body.textContent = 'Enable session storage in your browser to sign in to CompliTrack.';
            document.documentElement.hidden = false;
        }, { once: true });
        window.complitrackTabReady = new Promise(() => {});
        return;
    }

    window.tabUrl = value => {
        const url = new URL(value, location.href);
        if (url.origin !== location.origin || !/^https?:$/.test(url.protocol)) return value;
        url.searchParams.set('_tab', tab);
        return url.href;
    };
    const reloadContext = () => {
        document.documentElement.hidden = true;
        window.complitrackTabReady = new Promise(() => {});
        location.replace(window.tabUrl(location.href));
    };
    if (current.searchParams.get('_tab') !== tab) {
        reloadContext();
        return;
    }

    // Browsers copy sessionStorage when duplicating a tab. Let the existing
    // document retain its context and give the duplicate a fresh sign-in.
    let channel;
    let releaseLock;
    let ready = false;
    let leaving = false;
    const instance = generate();
    document.documentElement.hidden = true;
    window.complitrackTabReady = new Promise(resolve => {
        const reset = () => {
            if (leaving) return;
            leaving = true;
            tab = generate();
            sessionStorage.setItem(key, tab);
            channel?.close();
            reloadContext();
        };
        const finish = () => {
            if (leaving) return;
            ready = true;
            document.documentElement.hidden = false;
            resolve();
        };
        const fallback = () => {
            if (typeof BroadcastChannel !== 'undefined') {
                channel = new BroadcastChannel(`complitrack.tab.${tab}`);
                channel.onmessage = ({ data }) => {
                    if (data.type === 'probe' && data.instance !== instance) {
                        if (ready || instance < data.instance) channel.postMessage({ type: 'occupied', instance: data.instance });
                        else reset();
                    } else if (data.type === 'occupied' && data.instance === instance) reset();
                };
                channel.postMessage({ type: 'probe', instance });
            }
            setTimeout(finish, 250);
        };
        if (navigator.locks) {
            navigator.locks.request(`complitrack.tab.${tab}`, { ifAvailable: true }, lock => {
                if (!lock) { reset(); return; }
                finish();
                return new Promise(release => { releaseLock = release; });
            }).catch(fallback);
        } else fallback();
    });
    window.addEventListener('pagehide', () => { channel?.close(); releaseLock?.(); });

    const scopeElement = element => {
        if (!(element instanceof Element)) return;
        if (element.matches('a[href]')) {
            const href = element.getAttribute('href');
            if (href && !href.startsWith('#')) {
                const scoped = window.tabUrl(href);
                if (scoped !== element.href) element.href = scoped;
            }
        }
        if (element.matches('form')) {
            // Controls named "action" shadow form.action (the review decision buttons do this).
            // Compare attributes so observing our own URL update settles after one mutation.
            const currentAction = element.getAttribute('action');
            const action = window.tabUrl(currentAction || location.href);
            if (currentAction !== action) element.setAttribute('action', action);
            // GET form submission replaces the action query string.
            let field = element.querySelector('input[name="_tab"]');
            if (!field) {
                field = document.createElement('input');
                field.type = 'hidden'; field.name = '_tab'; element.append(field);
            }
            field.value = tab;
        }
        element.querySelectorAll('a[href], form').forEach(scopeElement);
    };
    new MutationObserver(records => {
        for (const record of records) {
            if (record.type === 'attributes') scopeElement(record.target);
            else record.addedNodes.forEach(scopeElement);
        }
    }).observe(document.documentElement, { subtree: true, childList: true, attributes: true, attributeFilter: ['href', 'action'] });
    document.addEventListener('submit', event => scopeElement(event.target), true);

    // Fetch is also used by server-rendered dialogs and HTML partials.
    const originalFetch = window.fetch.bind(window);
    window.fetch = (input, options) => {
        if (input instanceof Request) {
            const url = window.tabUrl(input.url);
            return originalFetch(url === input.url ? input : new Request(url, input), options);
        }
        return originalFetch(window.tabUrl(String(input)), options);
    };
})();
