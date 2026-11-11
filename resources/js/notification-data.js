const pending = new Map();

export function readNotifications(params) {
    const scope = document.body?.dataset.userId || '';
    const key = `${scope}:${JSON.stringify(Object.entries(params).sort(([a], [b]) => a.localeCompare(b)))}`;
    if (pending.has(key)) return pending.get(key).promise;
    const controller = new AbortController();
    const promise = window.axios.get('/api/v1/notifications', { params, signal: controller.signal })
        .finally(() => { if (pending.get(key)?.promise === promise) pending.delete(key); });
    pending.set(key, { promise, controller });
    return promise;
}

export function clearNotificationRequests() {
    for (const { controller } of pending.values()) controller.abort();
    pending.clear();
}
