<?php

namespace App\Livewire\AuditLog;

use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Livewire\Component;
use Livewire\WithPagination;
use Mary\Traits\Toast;
use OwenIt\Auditing\Models\Audit;

/**
 * Global audit log page. Admin-only via the `viewAuditLog` Gate
 * applied in the route middleware.
 */
class Index extends Component
{
    use Toast;
    use WithPagination;

    public ?int $filterUserId = null;

    public ?string $filterEvent = null;

    public ?string $filterAuditableType = null;

    public ?string $filterDateFrom = null;

    public ?string $filterDateTo = null;

    protected const PER_PAGE = 25;

    public function updating($property): void
    {
        if (str_starts_with($property, 'filter')) {
            $this->resetPage();
        }
    }

    public function clearFilters(): void
    {
        $this->reset(['filterUserId', 'filterEvent', 'filterAuditableType', 'filterDateFrom', 'filterDateTo']);
        $this->resetPage();
        $this->success(__('app.common.filters_cleared'));
    }

    public function render()
    {
        return view('livewire.audit-log.index', [
            'audits' => $this->fetchAudits(),
            'users' => User::orderBy('name')->get(['id', 'name']),
            'auditableTypes' => $this->auditableTypeOptions(),
            'eventOptions' => $this->eventOptions(),
        ]);
    }

    private function fetchAudits(): LengthAwarePaginator
    {
        return Audit::query()
            ->with(['user', 'auditable'])
            ->when($this->filterUserId, fn ($q) => $q->where('user_id', $this->filterUserId))
            ->when($this->filterEvent, fn ($q) => $q->where('event', $this->filterEvent))
            ->when($this->filterAuditableType, fn ($q) => $q->where('auditable_type', $this->filterAuditableType))
            ->when($this->filterDateFrom, fn ($q) => $q->where('created_at', '>=', $this->filterDateFrom))
            ->when($this->filterDateTo, fn ($q) => $q->where('created_at', '<=', $this->filterDateTo.' 23:59:59'))
            ->latest()
            ->paginate(self::PER_PAGE);
    }

    /**
     * @return array<array{id: string, name: string}>
     */
    private function auditableTypeOptions(): array
    {
        return [
            ['id' => Invoice::class, 'name' => __('app.nav.sell_invoices')],
            ['id' => InvoiceLine::class, 'name' => __('app.invoices.lines_section')],
            ['id' => Payment::class, 'name' => __('app.invoices.payment_section')],
        ];
    }

    /**
     * @return array<array{id: string, name: string}>
     */
    private function eventOptions(): array
    {
        return collect(['created', 'updated', 'deleted', 'email_sent', 'sdi_sent', 'sdi_accepted', 'sdi_rejected'])
            ->map(fn ($event) => [
                'id' => $event,
                'name' => __('app.audit.events.'.$event),
            ])
            ->all();
    }
}
