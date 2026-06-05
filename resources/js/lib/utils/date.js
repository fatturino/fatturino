const DATE_ONLY_PATTERN = /^(\d{4})-(\d{2})-(\d{2})$/;

export function isDateOnlyString(value) {
    return typeof value === 'string' && DATE_ONLY_PATTERN.test(value.trim());
}

export function parseDateOnly(value) {
    if (!isDateOnlyString(value)) return null;

    const [, year, month, day] = value.trim().match(DATE_ONLY_PATTERN);
    return new Date(Number(year), Number(month) - 1, Number(day));
}

export function toLocalDate(value) {
    if (!value) return null;

    if (value instanceof Date) {
        return Number.isNaN(value.getTime()) ? null : value;
    }

    if (isDateOnlyString(value)) {
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
