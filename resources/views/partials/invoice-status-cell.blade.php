@if($row->sdi_status)
    <x-badge :value="$row->sdi_status->label()" variant="$row->sdi_status->badgeVariant()" type="soft" />
@else
    <x-badge :value="$row->status->label()" variant="$row->status->badgeVariant()" type="soft" />
@endif
