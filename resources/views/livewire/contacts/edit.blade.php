<div>
    <x-header :title="$contact->name" separator>
        <x-slot:actions>
            <x-button :label="__('app.common.cancel')" link="/contacts" icon="o-x-mark" />
            <x-button :label="__('app.common.save')" wire:click="save" icon="o-check" class="btn-primary" spinner="save" />
        </x-slot:actions>
    </x-header>

    <x-form wire:submit="save">
        <div class="grid lg:grid-cols-2 gap-5">
            {{-- Main data --}}
            <div class="lg:col-span-2">
                <x-card :title="__('app.contacts.main_data')" separator>
                    <x-input :label="__('app.contacts.full_name')" wire:model="name" />
                </x-card>
            </div>

            {{-- Fiscal data --}}
            <div class="lg:col-span-2">
                <x-card :title="__('app.contacts.fiscal_data')" separator>
                    <div class="grid lg:grid-cols-2 gap-5">
                        <x-input :label="__('app.contacts.vat_number')" wire:model="vat_number" :hint="__('app.contacts.vat_number_hint')" />

                        @if($country === 'IT')
                            <x-input :label="__('app.contacts.tax_code')" wire:model="tax_code" :hint="__('app.contacts.tax_code_hint')" />
                            <x-input :label="__('app.contacts.sdi_code')" wire:model="sdi_code" :hint="__('app.contacts.sdi_code_hint')" />
                            <x-input :label="__('app.contacts.pec')" wire:model="pec" :hint="__('app.contacts.pec_hint')" />
                        @endif

                        <x-input :label="__('app.contacts.email')" wire:model="email" />
                    </div>
                </x-card>
            </div>

            {{-- Address --}}
            <div class="lg:col-span-2">
                <x-card :title="__('app.contacts.address_section')" separator>
                    <div class="grid lg:grid-cols-2 gap-5">
                        <x-select :label="__('app.contacts.country')" :options="$countries" wire:model.live="country" />
                        <x-input :label="__('app.contacts.address')" wire:model="address" />
                        <x-input :label="__('app.contacts.postal_code')" wire:model="postal_code" />
                        <x-input :label="__('app.contacts.city')" wire:model="city" />
                        <x-input :label="__('app.contacts.province')" wire:model="province" />
                    </div>
                </x-card>
            </div>
        </div>

    </x-form>
</div>
