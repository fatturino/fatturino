@props(['invoices'])

<article id="payment-reminders" class="dashboard-module p-2" x-data="paymentReminderCenter()">
    <div class="dashboard-module-inner p-5">
        <div class="flex items-start justify-between gap-4">
            <div>
                <h2 class="font-semibold text-content">Prossime scadenze e solleciti</h2>
                <p class="mt-1 text-sm text-content-muted">Prepara e verifica manualmente i promemoria di pagamento.</p>
            </div>
            <x-app-link href="/sell-invoices?payment=open" class="-m-2 inline-flex min-h-11 shrink-0 items-center p-2 text-sm font-semibold text-primary underline-offset-4 hover:underline">Vedi aperte</x-app-link>
        </div>

        <div class="mt-5 space-y-2">
            @forelse($invoices as $invoice)
                <div class="relative"><x-dashboard.due-date-row :invoice="$invoice" /><button type="button" class="mt-2 min-h-9 text-xs font-semibold text-primary underline-offset-4 hover:underline" @click="openReminder(@js($invoice))">Prepara email</button></div>
            @empty
                <div class="due-date-empty">
                    <p class="font-semibold text-content">Nessuna scadenza aperta</p>
                    <p class="mt-1 text-sm leading-6 text-content-muted">Non ci sono fatture aperte in scadenza entro sette giorni o già scadute.</p>
                </div>
            @endforelse
        </div>
    </div>
    <template x-teleport="body">
        <div x-show="open" x-trap.inert.noscroll="open" class="modal-viewport" role="dialog" aria-modal="true" aria-labelledby="payment-reminder-title">
            <div class="modal-backdrop bg-black/30" @click="close()"></div>
            <div class="modal-panel modal-panel--lg rounded-xl bg-white p-6">
                <div class="mb-5 flex items-start justify-between gap-4"><div><h2 id="payment-reminder-title" class="text-lg font-bold text-content" x-text="scenario === 'overdue' ? 'Invia sollecito di pagamento' : 'Invia promemoria di pagamento'"></h2><p class="mt-1 text-sm text-content-muted" x-text="invoice ? `Fattura ${invoice.number ?? `#${invoice.id}`} · ${invoice.contact ?? 'Cliente non associato'}` : ''"></p></div><button type="button" class="rounded-md p-1 text-content-muted hover:bg-surface-muted" @click="close()" aria-label="Chiudi"><x-icon name="o-x-mark" class="size-5" /></button></div>
                <template x-if="loading"><p class="py-10 text-center text-sm text-content-muted">Caricamento anteprima sollecito...</p></template>
                <form x-show="!loading" class="space-y-4" @submit.prevent="submit()">
                    <div><label for="reminder-recipient" class="mb-1 block text-sm font-semibold">Destinatario</label><input x-ref="recipient" id="reminder-recipient" x-model="email.recipientEmail" type="email" required class="input-field h-11 w-full rounded-md border border-border px-3"></div>
                    <div class="grid gap-4 sm:grid-cols-2"><div><label for="reminder-cc" class="mb-1 block text-sm font-semibold">CC (opzionale)</label><input id="reminder-cc" x-model="email.cc" type="email" class="input-field h-11 w-full rounded-md border border-border px-3"></div><div><label for="reminder-bcc" class="mb-1 block text-sm font-semibold">CCN (opzionale)</label><input id="reminder-bcc" x-model="email.bcc" type="email" class="input-field h-11 w-full rounded-md border border-border px-3"></div></div>
                    <div><label for="reminder-subject" class="mb-1 block text-sm font-semibold">Oggetto</label><input id="reminder-subject" x-model="email.subject" type="text" required class="input-field h-11 w-full rounded-md border border-border px-3"></div>
                    <label class="flex items-start justify-between gap-4 rounded-lg border border-border-light bg-surface-muted p-3"><span><span class="block text-sm font-semibold">Allega fattura</span><span class="block text-xs text-content-muted">Include il PDF nell'email.</span></span><input x-model="email.attachPdf" type="checkbox" class="mt-1 size-4 rounded border-border text-primary"></label>
                    <div><label for="reminder-body" class="mb-1 block text-sm font-semibold">Messaggio</label><textarea id="reminder-body" x-model="email.body" rows="12" required class="input-field w-full resize-y rounded-md border border-border px-3 py-2"></textarea></div>
                    <p x-show="error" x-text="error" class="text-sm text-error" role="alert"></p>
                    <div class="flex justify-end gap-3"><button type="button" class="btn-ghost" @click="close()">Annulla</button><button type="submit" class="btn-brand" :disabled="busy"><span x-text="busy ? 'Invio in corso...' : 'Conferma e invia' "></span></button></div>
                </form>
            </div>
        </div>
    </template>
</article>

@once
    <script>
        function paymentReminderCenter() {
            return {
                open: false, loading: false, busy: false, error: '', invoice: null, scenario: null,
                email: { recipientEmail: '', cc: '', bcc: '', subject: '', body: '', attachPdf: true },
                csrf() { return document.querySelector('meta[name="csrf-token"]')?.content ?? ''; },
                async request(url, options = {}) {
                    const response = await fetch(url, { ...options, headers: { Accept: 'application/json', 'X-CSRF-TOKEN': this.csrf(), ...(options.headers ?? {}) } });
                    const data = await response.json().catch(() => ({}));
                    if (!response.ok || data.success === false) throw new Error(Object.values(data.errors ?? {}).flat()[0] ?? data.error ?? 'Operazione non riuscita.');
                    return data;
                },
                async openReminder(invoice) {
                    this.invoice = invoice; this.scenario = invoice.reminder_scenario; this.open = true; this.loading = true; this.error = '';
                    try {
                        const data = await this.request(`/sell-invoices/${invoice.id}/payment-reminder-preview?scenario=${this.scenario}`);
                        const preview = data.preview ?? {};
                        this.email = { recipientEmail: preview.recipient_email ?? '', cc: preview.cc ?? '', bcc: preview.bcc ?? '', subject: preview.subject ?? '', body: preview.body ?? '', attachPdf: preview.attach_pdf ?? true };
                        this.$nextTick(() => this.$refs.recipient?.focus());
                    } catch (error) { this.close(); window.alert(error.message); } finally { this.loading = false; }
                },
                close() { if (!this.busy) { this.open = false; this.error = ''; } },
                async submit() {
                    this.busy = true; this.error = '';
                    try {
                        await this.request(`/sell-invoices/${this.invoice.id}/send-payment-reminder`, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ scenario: this.scenario, recipient_email: this.email.recipientEmail, cc: this.email.cc || null, bcc: this.email.bcc || null, subject: this.email.subject, body: this.email.body, attach_pdf: this.email.attachPdf }) });
                        this.open = false; window.location.reload();
                    } catch (error) { this.error = error.message; } finally { this.busy = false; }
                },
            };
        }
    </script>
@endonce
