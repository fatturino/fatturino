<script>
    import Authenticated from '$layouts/Authenticated.svelte'
    import InvoiceForm from './InvoiceForm.svelte'

    let { formData = {}, errors = {} } = $props()
</script>

<Authenticated>
    <InvoiceForm {formData} {errors} />
</Authenticated>
