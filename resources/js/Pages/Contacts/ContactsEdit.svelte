<script>
    import Authenticated from '../../Layouts/Authenticated.svelte'
    import Button from '$lib/components/ui/Button.svelte'
    import Input from '$lib/components/ui/Input.svelte'
    import Select from '$lib/components/ui/Select.svelte'
    import FormField from '$lib/components/ui/FormField.svelte'
    import { useForm } from '@inertiajs/svelte'
    import { showToast } from '$lib/toast.js'

    const props = $props()
    const countries = props.countries ?? []
    const errors = props.errors ?? {}
    const initialContact = props.contact ?? {}

    const form = useForm({
        name: initialContact?.name ?? '',
        email: initialContact?.email ?? '',
        vat_number: initialContact?.vat_number ?? '',
        tax_code: initialContact?.tax_code ?? '',
        sdi_code: initialContact?.sdi_code ?? '',
        pec: initialContact?.pec ?? '',
        country: initialContact?.country ?? 'IT',
        address: initialContact?.address ?? '',
        postal_code: initialContact?.postal_code ?? '',
        city: initialContact?.city ?? '',
        province: initialContact?.province ?? '',
    })

    function handleSubmit() {
        form.put(`/contacts/${initialContact.id}`, {
            preserveScroll: true,
            onSuccess: () => showToast('Contatto aggiornato.'),
        })
    }
</script>

<Authenticated>
    {#snippet headerActions()}
        <a href="/contacts" class="btn-outline text-sm">Annulla</a>
        <Button class="btn-brand text-sm" onclick={handleSubmit} disabled={form.processing}>
            {form.processing ? 'Salvataggio...' : 'Salva contatto'}
        </Button>
    {/snippet}

    <div class="page-shell pb-24 sm:pb-6 w-full">
        <div class="card-brand p-4 sm:p-6">
            <div class="grid grid-cols-2 gap-4">
                <FormField class="block col-span-2 sm:col-span-1" label="Nome" required error={errors.name} forId="contact-name">
                    <Input
                        id="contact-name"
                        class="mt-1 block w-full rounded-lg border border-brand-secondary/20 px-3 py-2 text-sm form-focus"
                        type="text"
                        bind:value={form.name}
                        state={errors.name ? 'error' : 'default'}
                        required
                    />
                </FormField>

                <FormField class="block col-span-2 sm:col-span-1" label="Nazione" required forId="contact-country">
                    <Select useNative
                        id="contact-country"
                        class="mt-1 block w-full rounded-lg border border-brand-secondary/20 px-3 py-2 text-sm form-focus bg-white"
                        bind:value={form.country}
                    >
                        {#each countries as country}
                            <option value={country.id}>{country.name}</option>
                        {/each}
                    </Select>
                </FormField>

                <FormField class="block col-span-2 sm:col-span-1" label="Partita IVA" error={errors.vat_number} forId="contact-vat-number">
                    <Input
                        id="contact-vat-number"
                        class="mt-1 block w-full rounded-lg border border-brand-secondary/20 px-3 py-2 text-sm form-focus"
                        type="text"
                        bind:value={form.vat_number}
                        state={errors.vat_number ? 'error' : 'default'}
                    />
                </FormField>

                <FormField class="block col-span-2 sm:col-span-1" label="Codice Fiscale" error={errors.tax_code} forId="contact-tax-code">
                    <Input
                        id="contact-tax-code"
                        class="mt-1 block w-full rounded-lg border border-brand-secondary/20 px-3 py-2 text-sm form-focus"
                        type="text"
                        bind:value={form.tax_code}
                        state={errors.tax_code ? 'error' : 'default'}
                    />
                </FormField>

                <FormField class="block col-span-2 sm:col-span-1" label="Email" error={errors.email} forId="contact-email">
                    <Input
                        id="contact-email"
                        class="mt-1 block w-full rounded-lg border border-brand-secondary/20 px-3 py-2 text-sm form-focus"
                        type="email"
                        bind:value={form.email}
                        state={errors.email ? 'error' : 'default'}
                    />
                </FormField>

                <FormField class="block col-span-2 sm:col-span-1" label="PEC" forId="contact-pec">
                    <Input
                        id="contact-pec"
                        class="mt-1 block w-full rounded-lg border border-brand-secondary/20 px-3 py-2 text-sm form-focus"
                        type="email"
                        bind:value={form.pec}
                    />
                </FormField>

                <FormField class="block col-span-2 sm:col-span-1" label="Codice SDI" forId="contact-sdi-code">
                    <Input
                        id="contact-sdi-code"
                        class="mt-1 block w-full rounded-lg border border-brand-secondary/20 px-3 py-2 text-sm form-focus"
                        type="text"
                        bind:value={form.sdi_code}
                    />
                </FormField>
            </div>

            <hr class="my-6 border-brand-secondary/10" />

            <h2 class="text-base font-semibold text-brand-deep mb-4">Indirizzo</h2>

            <div class="grid grid-cols-2 gap-4">
                <FormField class="block col-span-2" label="Indirizzo" forId="contact-address">
                    <Input
                        id="contact-address"
                        class="mt-1 block w-full rounded-lg border border-brand-secondary/20 px-3 py-2 text-sm form-focus"
                        type="text"
                        bind:value={form.address}
                    />
                </FormField>

                <FormField class="block col-span-2 sm:col-span-1" label="CAP" forId="contact-postal-code">
                    <Input
                        id="contact-postal-code"
                        class="mt-1 block w-full rounded-lg border border-brand-secondary/20 px-3 py-2 text-sm form-focus"
                        type="text"
                        bind:value={form.postal_code}
                    />
                </FormField>

                <FormField class="block col-span-2 sm:col-span-1" label="Citta" forId="contact-city">
                    <Input
                        id="contact-city"
                        class="mt-1 block w-full rounded-lg border border-brand-secondary/20 px-3 py-2 text-sm form-focus"
                        type="text"
                        bind:value={form.city}
                    />
                </FormField>

                <FormField class="block col-span-2 sm:col-span-1" label="Provincia" forId="contact-province">
                    <Input
                        id="contact-province"
                        class="mt-1 block w-full rounded-lg border border-brand-secondary/20 px-3 py-2 text-sm form-focus"
                        type="text"
                        maxlength="2"
                        bind:value={form.province}
                    />
                </FormField>
            </div>
        </div>
    </div>
</Authenticated>
