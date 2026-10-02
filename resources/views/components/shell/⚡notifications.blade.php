<?php

use App\Models\FiscalDocument;
use App\Models\InAppNotification;
use App\Services\InAppNotificationQuery;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

new class extends Component
{
    /** @var array<int, array<string, mixed>> */
    public array $notifications = [];

    public int $unreadCount = 0;

    public function mount(InAppNotificationQuery $query): void
    {
        $this->refresh($query);
    }

    public function load(InAppNotificationQuery $query): void
    {
        $page = $query->paginateFor(Auth::user(), [], 8);

        $this->notifications = array_map(
            fn (InAppNotification $notification) => $this->payload($notification),
            $page->items(),
        );
        $this->unreadCount = $query->unreadCountFor(Auth::user());
    }

    public function refresh(InAppNotificationQuery $query): void
    {
        $this->unreadCount = $query->unreadCountFor(Auth::user());
    }

    public function markAllRead(): void
    {
        $markedAt = now();

        InAppNotification::query()
            ->whereBelongsTo(Auth::user())
            ->whereNull('read_at')
            ->update(['read_at' => $markedAt, 'updated_at' => $markedAt]);

        foreach ($this->notifications as $index => $notification) {
            $this->notifications[$index]['read_at'] ??= $markedAt->toIso8601String();
        }

        $this->unreadCount = 0;
    }

    public function openNotification(string $id): void
    {
        $notification = InAppNotification::query()
            ->whereBelongsTo(Auth::user())
            ->findOrFail($id);

        $notification->markAsRead();
        $this->refresh(app(InAppNotificationQuery::class));

        if ($url = $this->resourceUrl($notification)) {
            $this->redirect($url, navigate: true);

            return;
        }

        $this->load(app(InAppNotificationQuery::class));
    }

    /** @return array<string, mixed> */
    private function payload(InAppNotification $notification): array
    {
        return [
            'id' => $notification->id,
            'severity' => $notification->severity,
            'title' => $notification->title,
            'body' => $notification->body,
            'read_at' => $notification->read_at?->toIso8601String(),
            'occurred_at' => $notification->occurred_at->toIso8601String(),
        ];
    }

    private function resourceUrl(InAppNotification $notification): ?string
    {
        if ($notification->action !== 'open_document' || $notification->resource_type !== 'fiscal_document' || ! $notification->resource_id) {
            return null;
        }

        $document = FiscalDocument::query()->find($notification->resource_id);

        return match ($document?->type) {
            'sales' => route('sell-invoices.edit', ['invoice' => $document]),
            'purchase' => route('purchase-invoices.edit', ['purchaseInvoice' => $document]),
            'self_invoice' => route('self-invoices.edit', ['selfInvoice' => $document]),
            'credit_note' => route('credit-notes.edit', ['creditNote' => $document]),
            'proforma' => route('proforma.edit', ['proformaInvoice' => $document]),
            default => null,
        };
    }
};
?>

<div
    wire:poll.visible.30s="refresh"
    x-data="{ open: false, relativeDate(value) { const seconds = Math.max(0, Math.floor((Date.now() - new Date(value).getTime()) / 1000)); if (seconds < 60) return 'adesso'; if (seconds < 3600) return `${Math.floor(seconds / 60)} min fa`; if (seconds < 86400) return `${Math.floor(seconds / 3600)} h fa`; return `${Math.floor(seconds / 86400)} g fa`; } }"
    class="relative"
    @keydown.escape.window="if (open) { open = false; $nextTick(() => $refs.trigger.focus()) }"
>
    <button
        x-ref="trigger"
        type="button"
        class="relative inline-flex size-11 items-center justify-center rounded-lg text-content transition hover:bg-surface-muted focus:outline-none focus:ring-2 focus:ring-primary/20"
        @click="open = !open; if (open) $wire.load()"
        :aria-expanded="open.toString()"
        aria-controls="notifications-panel"
        aria-label="Notifiche, {{ $unreadCount ? $unreadCount.' non lette' : 'nessuna non letta' }}"
    >
        <x-icon name="o-bell" class="size-5" />
        @if ($unreadCount > 0)
            <span class="absolute right-0.5 top-0.5 grid h-5 min-w-5 place-items-center rounded-full bg-primary px-1 text-[0.6875rem] font-bold leading-none text-white">{{ $unreadCount > 99 ? '99+' : $unreadCount }}</span>
        @endif
    </button>

    <section
        id="notifications-panel"
        x-cloak
        x-show="open"
        x-transition:enter="transition ease-out duration-150"
        x-transition:enter-start="opacity-0 translate-y-1 sm:scale-95"
        x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
        x-transition:leave="transition ease-in duration-100"
        x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
        x-transition:leave-end="opacity-0 translate-y-1 sm:scale-95"
        @click.outside="open = false"
        class="fixed inset-x-3 top-[4.75rem] z-50 overflow-hidden rounded-xl border border-border bg-white shadow-[var(--shadow-elevated)] sm:absolute sm:inset-x-auto sm:right-0 sm:top-[calc(100%+0.5rem)] sm:w-[25rem]"
        role="region"
        aria-label="Centro notifiche"
    >
        <div class="flex items-center justify-between gap-4 border-b border-border px-4 py-3.5">
            <div><h2 class="text-sm font-bold text-content">Notifiche</h2><p class="mt-0.5 text-xs text-content-muted">{{ $unreadCount ? $unreadCount.' non lette' : 'Tutto aggiornato' }}</p></div>
            <button type="button" class="rounded-md px-2 py-1 text-xs font-semibold text-primary transition hover:bg-primary-subtle disabled:cursor-not-allowed disabled:opacity-50" wire:click="markAllRead" wire:loading.attr="disabled" @disabled($unreadCount === 0)>Segna tutte come lette</button>
        </div>

        <div class="max-h-[min(32rem,calc(100dvh-8rem))] overflow-y-auto">
            @if (count($notifications) === 0)
                <div class="p-8 text-center"><div class="mx-auto grid size-10 place-items-center rounded-full bg-info-bg text-info"><x-icon name="o-bell" class="size-5" /></div><p class="mt-3 text-sm font-semibold text-content">Nessuna notifica</p><p class="mt-1 text-sm text-content-muted">Gli aggiornamenti importanti compariranno qui.</p></div>
            @else
                <div class="divide-y divide-border">
                    @foreach ($notifications as $notification)
                        <button type="button" class="group flex w-full gap-3 px-4 py-3.5 text-left transition hover:bg-surface-muted focus:bg-surface-muted" wire:click="openNotification('{{ $notification['id'] }}')" wire:loading.attr="disabled">
                            <span @class(['grid size-8 shrink-0 place-items-center rounded-full', 'bg-success-bg text-success' => $notification['severity'] === 'success', 'bg-warning-bg text-warning' => $notification['severity'] === 'warning', 'bg-danger-bg text-danger' => $notification['severity'] === 'error', 'bg-info-bg text-info' => $notification['severity'] === 'info'])>
                                @if ($notification['severity'] === 'success') <x-icon name="o-check-circle" class="size-4" /> @elseif ($notification['severity'] === 'warning') <x-icon name="o-exclamation-triangle" class="size-4" /> @elseif ($notification['severity'] === 'error') <x-icon name="o-x-circle" class="size-4" /> @else <x-icon name="o-information-circle" class="size-4" /> @endif
                            </span>
                            <span class="min-w-0 flex-1"><span class="flex items-start gap-2"><span class="min-w-0 flex-1 truncate text-sm font-semibold text-content">{{ $notification['title'] }}</span>@if (! $notification['read_at'])<span class="mt-1.5 size-2 shrink-0 rounded-full bg-primary"></span>@endif</span>@if ($notification['body'])<span class="mt-0.5 block line-clamp-2 text-sm leading-5 text-content-muted">{{ $notification['body'] }}</span>@endif<span class="mt-1.5 block text-xs text-content-muted" x-text="relativeDate('{{ $notification['occurred_at'] }}')"></span></span>
                        </button>
                    @endforeach
                </div>
            @endif
        </div>
    </section>
</div>
