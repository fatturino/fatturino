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
    indexPath="/self-invoices"
    endpointBase="/self-invoices"
    createLabel="Crea autofattura"
    updateLabel="Aggiorna autofattura"
    createSuccess="Autofattura creata."
    updateSuccess="Autofattura aggiornata."
    contactLabel="Fornitore *"
    contactPlaceholder="Seleziona fornitore..."
    contactTypeLabel="fornitore"
    numberEditable={true}
    showDocumentType={true}
    showDueDate={true}
    showRelatedInvoice={true}
    defaultDocumentType="TD17"
    showTabs={true}
    showPaymentTab={true}
    useSettingsDefaults={true}
    showLineDetails={true}
    showTaxOptions={false}
/>
