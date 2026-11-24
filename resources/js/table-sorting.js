import { onDocumentReady } from './dom-ready';

const collator = new Intl.Collator(undefined, { numeric: true, sensitivity: 'base' });

export function compareTableValues(left, right) {
    if (!left.text || !right.text) return left.text ? -1 : right.text ? 1 : 0;
    if (left.number !== null && right.number !== null) return left.number - right.number;
    return collator.compare(left.text, right.text);
}

function cellValue(cell) {
    const text = (cell?.querySelector('select')?.selectedOptions[0]?.textContent || cell?.innerText || cell?.textContent || '').replace(/\s+/g, ' ').trim();
    const date = cell?.querySelector('time[datetime]')?.getAttribute('datetime');
    if (date && Number.isFinite(Date.parse(date))) return { text, number: Date.parse(date) };
    if (/^(?:\d{4}-\d{2}-\d{2}|\d{1,2}\/\d{1,2}\/\d{4}|[A-Za-z]{3,9} \d{1,2},? \d{4})/.test(text)
        && Number.isFinite(Date.parse(text))) return { text, number: Date.parse(text) };
    const numeric = text.replaceAll(',', '').replace(/%$/, '');
    return { text, number: /^-?\d+(\.\d+)?$/.test(numeric) ? Number(numeric) : null };
}

onDocumentReady(() => {
    const root = document.querySelector('#main-content');
    if (!root) return;
    const states = new WeakMap();
    let scheduled = false;
    const observer = new MutationObserver(records => {
        if (records.some(record => record.target.closest?.('table') || [...record.addedNodes].some(node =>
            node.nodeType === 1 && (node.matches('table') || node.querySelector('table'))))) schedule();
    });
    const observe = () => observer.observe(root, { childList: true, subtree: true, characterData: true });

    function refresh() {
        scheduled = false;
        observer.disconnect();
        try {
            for (const table of root.querySelectorAll('table')) {
                if (table.closest('[data-no-sort], [aria-hidden="true"], .document-preview, .docx-wrapper')
                    || !table.tHead || table.tHead.rows.length !== 1) continue;
                const rows = [...table.tBodies].flatMap(body => [...body.rows]);
                // Grouped/merged rows must retain their association with the parent record.
                if (rows.length > 1 && rows.some(row => [...row.cells].some(cell => cell.rowSpan > 1 || cell.colSpan > 1))) continue;
                const state = states.get(table);
                for (const header of table.tHead.rows[0].cells) {
                    if (header.tagName !== 'TH' || header.colSpan > 1 || header.hasAttribute('data-no-sort')) continue;
                    let button = header.querySelector(':scope > .table-sort-button');
                    const label = (button?.querySelector('.table-sort-label') || header).textContent.replace(/\s+/g, ' ').trim();
                    if (!label || /^(actions?|details|select|download|preview)$/i.test(label)) continue;
                    if (!button) {
                        if (header.querySelector('button, a, input, select')) continue;
                        button = document.createElement('button');
                        button.type = 'button'; button.className = 'table-sort-button';
                        const content = document.createElement('span'); content.className = 'table-sort-label';
                        while (header.firstChild) content.append(header.firstChild);
                        const arrows = document.createElement('span'); arrows.className = 'table-sort-arrows';
                        arrows.setAttribute('aria-hidden', 'true');
                        for (const [direction, symbol] of [['asc', '▴'], ['desc', '▾']]) {
                            const arrow = document.createElement('span'); arrow.dataset.direction = direction; arrow.textContent = symbol; arrows.append(arrow);
                        }
                        button.append(content, arrows); header.append(button);
                    }
                    const active = state?.column === header.cellIndex;
                    header.setAttribute('aria-sort', active ? (state.direction === 'asc' ? 'ascending' : 'descending') : 'none');
                    button.title = `Sort current page by ${label}`;
                    button.setAttribute('aria-label', `Sort current page by ${label}, ${active && state.direction === 'asc' ? 'descending' : 'ascending'}`);
                }
                if (!state) continue;
                for (const body of table.tBodies) {
                    const original = [...body.rows];
                    if (original.some(row => row.cells.length <= state.column || row.cells[state.column].colSpan > 1)) continue;
                    const sorted = original.map((row, index) => ({ row, index, value: cellValue(row.cells[state.column]) }))
                        .sort((a, b) => {
                            if (!a.value.text || !b.value.text) return compareTableValues(a.value, b.value) || a.index - b.index;
                            return compareTableValues(a.value, b.value) * (state.direction === 'asc' ? 1 : -1) || a.index - b.index;
                        });
                    if (sorted.some((item, index) => item.row !== original[index])) {
                        for (const { row } of sorted) body.append(row);
                    }
                }
            }
        } finally { observe(); }
    }

    function schedule() {
        if (!scheduled) { scheduled = true; requestAnimationFrame(refresh); }
    }
    root.addEventListener('click', event => {
        const button = event.target.closest('.table-sort-button');
        if (!button) return;
        const header = button.closest('th'), table = header.closest('table');
        const current = states.get(table);
        states.set(table, { column: header.cellIndex, direction: current?.column === header.cellIndex && current.direction === 'asc' ? 'desc' : 'asc' });
        refresh();
    });
    document.addEventListener('alpine:initialized', schedule);
    refresh();
});
