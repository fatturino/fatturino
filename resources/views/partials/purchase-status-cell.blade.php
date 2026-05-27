@if($row->sdi_status)
    <x-badge :value="$row->sdi_status->label()" :variant="$row->sdi_status->badgeVariant()" type="soft" class="whitespace-nowrap" />
@else
    <x-badge :value="$row->status->label()" :variant="$row->status->badgeVariant()" type="soft" class="whitespace-nowrap" />
@endif
