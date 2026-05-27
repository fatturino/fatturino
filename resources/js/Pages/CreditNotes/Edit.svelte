<script>
    import Authenticated from '$layouts/Authenticated.svelte'
    import InvoiceForm from './InvoiceForm.svelte'

    let { creditNote = null, formData = {}, errors = {} } = $props()
    const isReadOnly = $derived.by(() => !creditNote?.is_sdi_editable || (creditNote?.date && new Date(creditNote.date).getFullYear() < new Date().getFullYear()))
</script>


<Authenticated>
    <InvoiceForm {creditNote} {formData} {errors} {isReadOnly} />
</Authenticated>
