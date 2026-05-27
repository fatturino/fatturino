<script>
    import Authenticated from '$layouts/Authenticated.svelte'
    import InvoiceForm from './InvoiceForm.svelte'

    let { invoice = null, formData = {}, errors = {} } = $props()
</script>

<Authenticated>
    <InvoiceForm {invoice} {formData} {errors} />
</Authenticated>
