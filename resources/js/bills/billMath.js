/**
 * Pure math helpers for the bill creation wizard.
 * No DOM/HTTP dependencies — safe to unit-test.
 */

export function fmt(n) {
    return 'NT$' + Number(n).toLocaleString();
}

export function monthsOptions(startDate) {
    const d = startDate ? new Date(startDate) : null;
    const isFirst = d && d.getDate() === 1;
    const isLastDay = d && (() => {
        const last = new Date(d.getFullYear(), d.getMonth() + 1, 0);
        return d.getDate() === last.getDate();
    })();

    const opts = [];
    if (d && !isFirst && !isLastDay) opts.push({ v: 0, l: '月底' });
    for (let m = 1; m <= 36; m++) {
        let label = `${m} 個月`;
        if (m === 6)  label = '6 個月（半年）';
        if (m === 12) label = '12 個月（年繳）';
        if (m === 24) label = '24 個月（2 年繳）';
        if (m === 36) label = '36 個月（3 年繳）';
        opts.push({ v: m, l: label });
    }
    return opts;
}

export function paymentTypeFromMonths(m) {
    if (m === 1) return 1;
    if (m === 3) return 2;
    if (m === 12 || m === 24 || m === 36) return 3;
    return null;
}
