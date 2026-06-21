// ── 格式化工具（zh-TW，零外部套件）────────────────────────────
const timeFmt = new Intl.DateTimeFormat('zh-TW', { hour: '2-digit', minute: '2-digit', hour12: false });
const fullDateFmt = new Intl.DateTimeFormat('zh-TW', { year: 'numeric', month: 'long', day: 'numeric' });
const shortDateFmt = new Intl.DateTimeFormat('zh-TW', { month: 'numeric', day: 'numeric' });

export const dayKey = (d) => `${d.getFullYear()}-${d.getMonth()}-${d.getDate()}`;

export const isSameDay = (a, b) => dayKey(a) === dayKey(b);

export const yesterdayOf = (now) => {
    const y = new Date(now);
    y.setDate(now.getDate() - 1);
    return y;
};

export const formatTime = (d) => timeFmt.format(d);

export const formatDayLabel = (d) => {
    const now = new Date();
    if (isSameDay(d, now)) {
        return '今天';
    }
    if (isSameDay(d, yesterdayOf(now))) {
        return '昨天';
    }
    return fullDateFmt.format(d);
};

export const formatListTime = (iso) => {
    const d = new Date(iso);
    const now = new Date();
    if (isSameDay(d, now)) {
        return formatTime(d);
    }
    if (isSameDay(d, yesterdayOf(now))) {
        return '昨天';
    }
    return shortDateFmt.format(d);
};

export const initials = (name) => {
    const s = (name || '').trim();
    if (!s) {
        return '?';
    }
    const parts = s.split(/\s+/);
    if (parts.length >= 2) {
        return (Array.from(parts[0])[0] + Array.from(parts[1])[0]).toUpperCase();
    }
    return Array.from(s)[0];
};

export const escapeHtml = (text) => {
    const div = document.createElement('div');
    div.textContent = text ?? '';
    return div.innerHTML;
};
