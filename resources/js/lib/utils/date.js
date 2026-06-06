const DATE_ONLY_PATTERN = /^(\d{4})-(\d{2})-(\d{2})$/;
const DATE_PREFIX_PATTERN = /^(\d{4}-\d{2}-\d{2})(?:$|[T\s])/;

export function normalizeDateOnlyString(value) {
    if (typeof value !== 'string') return '';

    const normalized = value.trim().replace(' ', 'T');
    const match = normalized.match(DATE_PREFIX_PATTERN);
    return match ? match[1] : '';
}

export function extractDatePrefix(value) {
    return normalizeDateOnlyString(value) || null;
}

export function isDateOnlyString(value) {
    return typeof value === 'string' && DATE_ONLY_PATTERN.test(value.trim());
}

export function parseDateOnly(value) {
    const dateOnly = isDateOnlyString(value) ? value.trim() : extractDatePrefix(value);
    if (!dateOnly) return null;

    const [, year, month, day] = dateOnly.match(DATE_ONLY_PATTERN);
    return new Date(Number(year), Number(month) - 1, Number(day));
}

export function getTodayLocalDateString() {
    const now = new Date();
    const year = String(now.getFullYear());
    const month = String(now.getMonth() + 1).padStart(2, '0');
    const day = String(now.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
}

export function toLocalDate(value) {
    if (!value) return null;

    if (value instanceof Date) {
        return Number.isNaN(value.getTime()) ? null : value;
    }

    if (typeof value === 'string' && extractDatePrefix(value)) {
        return parseDateOnly(value);
    }

    const date = new Date(value);
    return Number.isNaN(date.getTime()) ? null : date;
}

export function formatLocalDate(value, locale = 'it-IT', options) {
    const date = toLocalDate(value);
    if (!date) return '';
    return new Intl.DateTimeFormat(locale, options).format(date);
}

export function getLocalDateYear(value) {
    const date = toLocalDate(value);
    return date ? date.getFullYear() : null;
}
