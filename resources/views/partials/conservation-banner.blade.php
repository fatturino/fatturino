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
        variant="success" class="mb-6"
    />
@else
    <x-alert
        :title="__('app.conservation.banner_title')"
        :description="__('app.conservation.banner_description')"
        icon="o-archive-box"
        variant="warning" class="mb-6"
    >
        <x-slot:actions>
            <x-button
                :label="__('app.conservation.link_label')"
                icon-right="o-arrow-top-right-on-square"
                link="https://ivaservizi.agenziaentrate.gov.it"
                external
                variant="outline" size="sm"
            />
            <x-button
                :label="__('app.conservation.acknowledge_button')"
                wire:click="acknowledgeConservation"
                icon="o-check"
                variant="primary" size="sm"
                spinner="acknowledgeConservation"
            />
        </x-slot:actions>
    </x-alert>
@endif
