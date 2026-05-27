export const InvoiceContentType = {
    SALES: 'sales',
    SELF_INVOICE: 'self_invoice',
    CREDIT_NOTE: 'credit_note',
    PURCHASE: 'purchase',
    PROFORMA: 'proforma',
}

function createValidateOrSendAction(item, callbacks) {
    if (!item?.is_sdi_editable) {
        return {
            id: 'validate_or_send_sdi',
            label: 'Valida XML / Invia SDI',
            disabled: true,
        }
    }

    if (item.status === 'draft' && callbacks?.validateXml) {
        return {
            id: 'validate_or_send_sdi',
            label: 'Valida XML / Invia SDI',
            onSelect: () => callbacks.validateXml(item),
        }
    }

    if (item.status === 'xml_validated' && callbacks?.sendToSdi) {
        return {
            id: 'validate_or_send_sdi',
            label: 'Valida XML / Invia SDI',
            onSelect: () => callbacks.sendToSdi(item),
        }
    }

    return {
        id: 'validate_or_send_sdi',
        label: 'Valida XML / Invia SDI',
        disabled: true,
    }
}

export function buildInvoiceContextActions({ contentType, item, links = {}, callbacks = {} }) {
    const actions = []

    if (links.edit) {
        actions.push({ id: 'edit', label: 'Modifica', href: links.edit })
    }

    if (callbacks.recordPayment) {
        actions.push({
            id: 'record_payment',
            label: 'Segna pagamento',
            onSelect: () => callbacks.recordPayment(item),
        })
    }

    if (contentType === InvoiceContentType.PURCHASE) {
        return actions
    }

    if (links.xml) {
        actions.push({ id: 'xml', label: 'Scarica XML', href: links.xml })
    }

    if ([InvoiceContentType.SALES, InvoiceContentType.SELF_INVOICE, InvoiceContentType.CREDIT_NOTE].includes(contentType)) {
        actions.push(createValidateOrSendAction(item, callbacks))
    }

    if (links.pdf) {
        actions.push({ id: 'pdf', label: 'Download PDF', href: links.pdf })
    }

    if (contentType === InvoiceContentType.PROFORMA && callbacks.convertToInvoice) {
        actions.push({
            id: 'convert_to_invoice',
            label: 'Converti in fattura',
            onSelect: () => callbacks.convertToInvoice(item),
        })
    }

    actions.push({
        id: 'send_email',
        label: 'Invia Email',
        disabled: !item?.contact?.email || !callbacks.sendEmail,
        onSelect: callbacks.sendEmail ? () => callbacks.sendEmail(item) : undefined,
    })

    return actions
}
