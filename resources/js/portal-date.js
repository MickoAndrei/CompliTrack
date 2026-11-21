export function portalDate(value, { dateOnly = false, precise = false } = {}) {
    if (!value) return 'Not recorded';
    const text = String(value), isDate = /^\d{4}-\d{2}-\d{2}$/.test(text);
    const date = new Date(isDate ? `${text}T00:00:00Z` : text);
    if (Number.isNaN(date.getTime())) return 'Date unavailable';
    if (dateOnly || isDate) return new Intl.DateTimeFormat('en-PH', { timeZone: isDate ? 'UTC' : 'Asia/Manila', year: 'numeric', month: 'short', day: 'numeric' }).format(date);
    return new Intl.DateTimeFormat('en-PH', { timeZone: 'Asia/Manila', year: 'numeric', month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit', ...(precise ? {second: '2-digit'} : {}) }).format(date) + ' PST (UTC+08:00)';
}
