{{--
    Reusable conservation acknowledgment banner.
    Hosting components must expose:
        - bool $conservationAcknowledged
        - method acknowledgeConservation()
--}}
@if($conservationAcknowledged)
    <x-alert
        :title="__('app.conservation.acknowledged_title')"
        :description="__('app.conservation.acknowledged_description')"
        icon="o-check-circle"
        class="mb-6 alert-success"
    />
@else
    <x-alert
        :title="__('app.conservation.banner_title')"
        :description="__('app.conservation.banner_description')"
        icon="o-archive-box"
        class="mb-6 alert-warning"
    >
        <x-slot:actions>
            <x-button
                :label="__('app.conservation.link_label')"
                icon-right="o-arrow-top-right-on-square"
                link="https://ivaservizi.agenziaentrate.gov.it"
                external
                class="btn-sm btn-outline"
            />
            <x-button
                :label="__('app.conservation.acknowledge_button')"
                wire:click="acknowledgeConservation"
                icon="o-check"
                class="btn-sm btn-primary"
                spinner="acknowledgeConservation"
            />
        </x-slot:actions>
    </x-alert>
@endif
