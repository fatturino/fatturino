<script>
    import BaseInvoiceForm from '$lib/components/forms/BaseInvoiceForm.svelte'

    let {
        formData = {},
        errors = {},
        invoice = null,
        isReadOnly = false,
    } = $props()
</script>

<BaseInvoiceForm
    {formData}
    {errors}
    {invoice}
    {isReadOnly}
    indexPath="/purchase-invoices"
    endpointBase="/purchase-invoices"
    updateLabel="Aggiorna fattura"
    updateSuccess="Fattura di acquisto aggiornata."
    contactLabel="Fornitore *"
    contactPlaceholder="Seleziona fornitore..."
    numberEditable={true}
    showDueDate={true}
    showPaidSummary={true}
    showTabs={true}
    showPaymentTab={true}
    useSettingsDefaults={true}
    showLineDetails={true}
    showTaxOptions={false}
/>
