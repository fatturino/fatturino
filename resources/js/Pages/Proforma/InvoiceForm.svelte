<script>
    import BaseInvoiceForm from '$lib/components/forms/BaseInvoiceForm.svelte'
    import { getLocalDateYear } from '$lib/utils/date.js'

    let { invoice = null, formData = {}, errors = {} } = $props()

    const isReadOnly = $derived.by(() => {
        const isEdit = invoice !== null
        const currentYear = new Date().getFullYear()
        const invoiceYear = invoice?.date ? getLocalDateYear(invoice.date) ?? currentYear : currentYear
        return isEdit && (
            invoice?.status === 'converted' ||
            invoice?.status === 'cancelled' ||
            invoiceYear < currentYear
        )
    })
</script>

<BaseInvoiceForm
    {formData}
    {errors}
    {invoice}
    {isReadOnly}
    indexPath="/proforma"
    endpointBase="/proforma"
    createLabel="Crea proforma"
    updateLabel="Aggiorna proforma"
    createSuccess="Proforma creata."
    updateSuccess="Proforma aggiornata."
    contactLabel="Cliente *"
    contactPlaceholder="Seleziona cliente..."
    showDueDate={true}
    showTabs={true}
    showPaymentTab={true}
    useSettingsDefaults={true}
    showLineDetails={true}
    showTaxOptions={true}
/>
