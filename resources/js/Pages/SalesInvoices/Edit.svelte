<script>
    import Authenticated from '$layouts/Authenticated.svelte'
    import InvoiceForm from './InvoiceForm.svelte'
    import { getLocalDateYear } from '$lib/utils/date.js'

    let { invoice = null, formData = {}, errors = {} } = $props()
    const isReadOnly = $derived.by(() => !invoice?.is_sdi_editable || (invoice?.date && getLocalDateYear(invoice.date) < new Date().getFullYear()))
</script>

<Authenticated>
    <InvoiceForm {invoice} {formData} isReadOnly={isReadOnly} {errors} />
</Authenticated>
