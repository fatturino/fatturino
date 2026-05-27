function euroToCents(value) {
    return Math.round((Number(value) || 0) * 100)
}

function normalizeOptional(value) {
    return value === '' ? null : value
}

function mapLineWithDiscount(line) {
    const quantity = Number(line.quantity) || 0
    const unitPrice = Number(line.unit_price) || 0
    const gross = quantity * unitPrice
    const discountPercent = line.discount_percent === '' || line.discount_percent === null || line.discount_percent === undefined
        ? null
        : Number(line.discount_percent)
    const discountedTotal = discountPercent && discountPercent > 0
        ? gross * (1 - discountPercent / 100)
        : gross

    return {
        description: line.description,
        quantity,
        unit_of_measure: normalizeOptional(line.unit_of_measure),
        unit_price: euroToCents(unitPrice),
        discount_percent: discountPercent,
        discount_amount: discountPercent && discountPercent > 0 ? euroToCents(gross - discountedTotal) : null,
        vat_rate: line.vat_rate,
        total: euroToCents(discountedTotal),
    }
}

function mapLineBasic(line) {
    const quantity = Number(line.quantity) || 0
    const unitPrice = Number(line.unit_price) || 0

    return {
        description: line.description,
        quantity,
        unit_of_measure: normalizeOptional(line.unit_of_measure),
        unit_price: euroToCents(unitPrice),
        discount_percent: null,
        discount_amount: null,
        vat_rate: line.vat_rate,
        total: euroToCents(quantity * unitPrice),
    }
}

export function mapSalesInput(form, lines) {
    return {
        contact_id: Number(form.contact_id),
        sequence_id: Number(form.sequence_id),
        date: form.date,
        due_date: normalizeOptional(form.due_date),
        document_type: form.document_type,
        notes: normalizeOptional(form.notes),
        withholding_tax_enabled: !!form.withholding_tax_enabled,
        withholding_tax_percent: normalizeOptional(form.withholding_tax_percent),
        fund_enabled: !!form.fund_enabled,
        fund_type: normalizeOptional(form.fund_type),
        fund_percent: normalizeOptional(form.fund_percent),
        fund_vat_rate: normalizeOptional(form.fund_vat_rate),
        fund_has_deduction: !!form.fund_has_deduction,
        stamp_duty_applied: !!form.stamp_duty_applied,
        payment_method: normalizeOptional(form.payment_method),
        payment_terms: normalizeOptional(form.payment_terms),
        bank_name: normalizeOptional(form.bank_name),
        bank_iban: normalizeOptional(form.bank_iban),
        vat_payability: form.vat_payability,
        split_payment: !!form.split_payment,
        lines: lines.map(mapLineWithDiscount),
    }
}

export function mapBasicDocumentInput(form, lines) {
    return {
        contact_id: Number(form.contact_id),
        sequence_id: Number(form.sequence_id),
        date: form.date,
        due_date: normalizeOptional(form.due_date),
        document_type: normalizeOptional(form.document_type),
        related_invoice_number: normalizeOptional(form.related_invoice_number),
        related_invoice_date: normalizeOptional(form.related_invoice_date),
        notes: normalizeOptional(form.notes),
        lines: lines.map(mapLineBasic),
    }
}

export function mapNativeInvoicesListResult(result, resultKey, query, extra = {}) {
    const root = result?.[resultKey] ?? {}
    const paginator = root?.paginatorInfo
    const rows = root?.data ?? []

    const currentPage = paginator?.currentPage ?? 1
    const lastPage = paginator?.lastPage ?? 1

    return {
        invoices: {
            data: rows,
            current_page: currentPage,
            last_page: lastPage,
            from: paginator?.firstItem ?? 0,
            to: paginator?.lastItem ?? 0,
            total: paginator?.total ?? rows.length,
            links: buildLinks(currentPage, lastPage, query),
        },
        stats: extra.stats ?? {},
        statusOptions: extra.statusOptions ?? [],
        paymentOptions: extra.paymentOptions ?? [],
    }
}

function buildLinks(currentPage, lastPage, query) {
    const links = []
    for (let page = 1; page <= lastPage; page++) {
        const url = new URL(window.location.href)
        url.searchParams.set('page', String(page))
        if (query.search) url.searchParams.set('search', query.search)
        else url.searchParams.delete('search')
        if (query.status) url.searchParams.set('status', query.status)
        else url.searchParams.delete('status')
        if (query.payment) url.searchParams.set('payment', query.payment)
        else url.searchParams.delete('payment')
        url.searchParams.set('fiscal_year', String(query.fiscalYear))

        links.push({
            url: url.toString(),
            label: String(page),
            active: page === currentPage,
        })
    }

    return links
}
