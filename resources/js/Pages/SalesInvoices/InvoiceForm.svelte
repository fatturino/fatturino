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
    indexPath="/sell-invoices"
    endpointBase="/sell-invoices"
    createLabel="Crea fattura"
    updateLabel="Aggiorna fattura"
    createSuccess="Fattura creata."
    updateSuccess="Fattura aggiornata."
    contactLabel="Cliente *"
    contactPlaceholder="Seleziona cliente..."
    showDocumentType={true}
    showSequence={false}
    showDueDate={true}
    showTabs={true}
    showPaymentTab={true}
    useSettingsDefaults={true}
    showLineDetails={true}
    showTaxOptions={true}
/>
