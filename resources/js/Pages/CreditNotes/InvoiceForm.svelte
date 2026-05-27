<script>
    import BaseInvoiceForm from '$lib/components/forms/BaseInvoiceForm.svelte'

    let {
        formData = {},
        errors = {},
        creditNote = null,
        isReadOnly = false,
    } = $props()

    const invoice = $derived(creditNote)
</script>

<BaseInvoiceForm
    {formData}
    {errors}
    {invoice}
    {isReadOnly}
    indexPath="/credit-notes"
    endpointBase="/credit-notes"
    createLabel="Crea nota di credito"
    updateLabel="Aggiorna nota di credito"
    createSuccess="Nota di credito creata."
    updateSuccess="Nota di credito aggiornata."
    contactLabel="Cliente *"
    contactPlaceholder="Seleziona cliente..."
    contactTypeLabel="originale"
    showDocumentType={false}
    showDueDate={false}
    showRelatedInvoice={true}
    showTabs={true}
    showPaymentTab={false}
    useSettingsDefaults={true}
    showLineDetails={true}
    showTaxOptions={false}
/>
