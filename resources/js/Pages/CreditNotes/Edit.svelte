<script>
    import Authenticated from '$layouts/Authenticated.svelte'
    import InvoiceForm from './InvoiceForm.svelte'
    import { getLocalDateYear } from '$lib/utils/date.js'

    let { creditNote = null, formData = {}, errors = {} } = $props()
    const isReadOnly = $derived.by(() => !creditNote?.is_sdi_editable || (creditNote?.date && getLocalDateYear(creditNote.date) < new Date().getFullYear()))
</script>


<Authenticated>
    <InvoiceForm {creditNote} {formData} {errors} {isReadOnly} />
</Authenticated>
