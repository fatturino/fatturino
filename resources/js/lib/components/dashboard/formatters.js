export function formatCurrency(value) {
    return new Intl.NumberFormat('it-IT', { style: 'currency', currency: 'EUR', minimumFractionDigits: 0 }).format((value ?? 0) / 100)
}

export function formatPercent(value) {
    const sign = value > 0 ? '+' : ''
    return `${sign}${value.toFixed(1)}%`
}

export function shortDate(dateValue) {
    if (!dateValue) return 'n/d'
    const date = new Date(dateValue)
    if (Number.isNaN(date.getTime())) return 'n/d'
    return new Intl.DateTimeFormat('it-IT', { day: '2-digit', month: '2-digit' }).format(date)
}
