<script>
    import Authenticated from '$layouts/Authenticated.svelte'
    import InvoiceForm from './InvoiceForm.svelte'

    let { invoice = null, formData = {}, errors = {} } = $props()
    const isReadOnly = $derived.by(() => !invoice?.is_sdi_editable || (invoice?.date && new Date(invoice.date).getFullYear() < new Date().getFullYear()))
</script>

<Authenticated>
    <InvoiceForm {invoice} {formData} isReadOnly={isReadOnly} {errors} />
</Authenticated>
