<div x-cloak>
    <div x-show="notice.show" x-transition class="fixed bottom-6 right-6 z-[80] max-w-md rounded-lg px-4 py-3 text-sm font-semibold text-white shadow-lg" :class="notice.type === 'error' ? 'bg-error' : 'bg-success'" role="status" aria-live="polite"><span x-text="notice.message"></span><button type="button" class="ml-4 opacity-80 hover:opacity-100" @click="notice.show = false" aria-label="Chiudi"><x-icon name="o-x-mark" class="inline size-4" /></button></div>
    <div x-show="emailOpen" class="fixed inset-0 z-[70] overflow-y-auto p-4" role="dialog" aria-modal="true" aria-labelledby="document-email-title">
        <div class="fixed inset-0 bg-black/30" @click="closeEmail()"></div>
        <div class="relative mx-auto my-6 w-full max-w-2xl rounded-xl bg-white p-6 shadow-xl">
            <div class="mb-5 flex items-center justify-between gap-4"><h2 id="document-email-title" class="text-lg font-bold text-content" x-text="`Invia email ${documentLabel()}`"></h2><button type="button" class="rounded-md p-1 text-content-muted hover:bg-surface-muted" @click="closeEmail()" aria-label="Chiudi"><x-icon name="o-x-mark" class="size-5" /></button></div>
            <template x-if="emailLoading"><p class="py-10 text-center text-sm text-content-muted">Caricamento anteprima email...</p></template>
            <form x-show="!emailLoading" class="space-y-4" @submit.prevent="submitEmail()">
                <div><label for="email-recipient" class="mb-1 block text-sm font-semibold">Destinatario</label><input id="email-recipient" x-model="email.recipientEmail" type="email" required class="input-field h-11 w-full rounded-md border border-border px-3"></div>
                <div class="grid gap-4 sm:grid-cols-2"><div><label for="email-cc" class="mb-1 block text-sm font-semibold">CC (opzionale)</label><input id="email-cc" x-model="email.cc" type="email" class="input-field h-11 w-full rounded-md border border-border px-3"></div><div><label for="email-bcc" class="mb-1 block text-sm font-semibold">CCN (opzionale)</label><input id="email-bcc" x-model="email.bcc" type="email" class="input-field h-11 w-full rounded-md border border-border px-3"></div></div>
                <div><label for="email-subject" class="mb-1 block text-sm font-semibold">Oggetto</label><input id="email-subject" x-model="email.subject" type="text" class="input-field h-11 w-full rounded-md border border-border px-3"></div>
                <label class="flex items-start justify-between gap-4 rounded-lg border border-border-light bg-surface-muted p-3"><span><span class="block text-sm font-semibold">Allega documento</span><span class="block text-xs text-content-muted">Include il PDF nell'email.</span></span><input x-model="email.attachPdf" type="checkbox" class="mt-1 size-4 rounded border-border text-primary"></label>
                <div><label for="email-body" class="mb-1 block text-sm font-semibold">Messaggio</label><textarea id="email-body" x-model="email.body" rows="12" class="input-field w-full resize-y rounded-md border border-border px-3 py-2"></textarea></div>
                <p x-show="error" x-text="error" class="text-sm text-error"></p>
                <div class="flex justify-end gap-3"><button type="button" class="btn-ghost" @click="closeEmail()">Annulla</button><button type="submit" class="btn-brand" :disabled="busy"><span x-text="busy ? 'Invio in corso...' : 'Invia email'"></span></button></div>
            </form>
        </div>
    </div>

    <div x-show="paymentOpen" class="fixed inset-0 z-[70] overflow-y-auto p-4" role="dialog" aria-modal="true" aria-labelledby="document-payment-title">
        <div class="fixed inset-0 bg-black/30" @click="closePayment()"></div>
        <div class="relative mx-auto my-6 w-full max-w-xl rounded-xl bg-white p-6 shadow-xl">
            <div class="mb-5 flex items-center justify-between gap-4"><div><h2 id="document-payment-title" class="text-lg font-bold text-content" x-text="`${paymentCopy().title} ${documentLabel()}`"></h2><p class="mt-1 text-sm text-content-muted" x-text="`Inserisci importo e, se disponibile, la data ${paymentCopy().action}.`"></p></div><button type="button" class="rounded-md p-1 text-content-muted hover:bg-surface-muted" @click="closePayment()" aria-label="Chiudi"><x-icon name="o-x-mark" class="size-5" /></button></div>
            <form class="space-y-4" @submit.prevent="savePayment()">
                <div class="rounded-lg border border-border-light p-4"><div class="mb-3 flex items-center justify-between"><p class="text-sm font-semibold" x-text="editingPaymentId ? paymentCopy().editTitle : paymentCopy().newTitle"></p><button x-show="editingPaymentId" type="button" class="text-sm font-semibold text-primary" @click="resetPaymentForm()">Annulla modifica</button></div><div><label for="payment-amount" class="mb-1 block text-sm font-semibold">Importo (EUR)</label><input id="payment-amount" x-model="payment.amount" type="number" min="0.01" step="0.01" required class="input-field h-11 w-full rounded-md border border-border px-3"></div><div class="mt-3 flex gap-2"><button type="button" class="btn-outline px-3 py-1 text-xs" @click="setQuickPayment(1)">Tutto</button><button type="button" class="btn-outline px-3 py-1 text-xs" @click="setQuickPayment(.5)">1/2</button><button type="button" class="btn-outline px-3 py-1 text-xs" @click="setQuickPayment(1 / 3)">1/3</button></div><div class="mt-3"><label for="payment-date" class="mb-1 block text-sm font-semibold" x-text="paymentCopy().dateLabel"></label><input id="payment-date" x-model="payment.paidAt" type="date" class="input-field h-11 w-full rounded-md border border-border px-3"></div><div class="mt-3"><label for="payment-reference" class="mb-1 block text-sm font-semibold" x-text="paymentCopy().referenceLabel"></label><input id="payment-reference" x-model="payment.reference" type="text" class="input-field h-11 w-full rounded-md border border-border px-3" placeholder="CRO, TRN, ID operazione"></div><div class="mt-3"><label for="payment-notes" class="mb-1 block text-sm font-semibold" x-text="paymentCopy().notesLabel"></label><input id="payment-notes" x-model="payment.notes" type="text" class="input-field h-11 w-full rounded-md border border-border px-3" placeholder="Es. saldo fattura aprile"></div><div class="mt-3"><label for="payment-bank" class="mb-1 block text-sm font-semibold" x-text="paymentCopy().bankLabel"></label><input id="payment-bank" x-model="payment.bankName" type="text" class="input-field h-11 w-full rounded-md border border-border px-3" placeholder="Es. Intesa Sanpaolo"></div></div>
                <div class="rounded-lg border border-border-light bg-surface-muted p-3 text-sm"><div class="flex justify-between"><span x-text="paymentCopy().totalPaidLabel"></span><strong x-text="money(selectedDocument?.totalPaid)"></strong></div><div class="mt-1 flex justify-between"><span x-text="paymentCopy().remainingLabel"></span><strong x-text="money(remainingBalance())"></strong></div></div>
                <div class="rounded-lg border border-border-light p-4"><p class="mb-3 text-sm font-semibold" x-text="paymentCopy().listTitle"></p><template x-if="selectedDocument?.payments?.length"><div class="space-y-3"><template x-for="entry in selectedDocument.payments" :key="entry.id"><div class="flex items-start justify-between gap-4 border-b border-border-light pb-3 last:border-0 last:pb-0"><div class="min-w-0 text-sm"><p class="font-semibold" x-text="money(entry.amount)"></p><p class="text-content-muted" x-text="formatDate(entry.paidAt)"></p><p x-show="entry.reference" class="text-content-muted" x-text="`Rif: ${entry.reference}`"></p><p x-show="entry.notes" class="text-content-muted" x-text="`Causale: ${entry.notes}`"></p><p x-show="entry.bankName" class="text-content-muted" x-text="`Banca: ${entry.bankName}`"></p></div><div class="flex shrink-0 gap-2"><button type="button" class="text-sm font-semibold text-primary" @click="editPayment(entry)">Modifica</button><button type="button" class="text-sm font-semibold text-error" :disabled="busy" @click="deletePayment(entry)">Elimina</button></div></div></template></div></template><p x-show="!selectedDocument?.payments?.length" class="text-sm text-content-muted" x-text="paymentCopy().emptyListLabel"></p></div>
                <p x-show="error" x-text="error" class="text-sm text-error"></p>
                <div class="flex justify-end gap-3"><button type="button" class="btn-ghost" @click="closePayment()">Chiudi</button><button type="submit" class="btn-brand" :disabled="busy"><span x-text="busy ? 'Salvataggio...' : (editingPaymentId ? paymentCopy().updateLabel : paymentCopy().saveLabel)"></span></button></div>
            </form>
        </div>
    </div>

    <div x-show="confirmOpen" class="fixed inset-0 z-[70] flex items-center justify-center p-4" role="dialog" aria-modal="true" aria-labelledby="document-confirm-title">
        <div class="fixed inset-0 bg-black/30" @click="closeConfirm()"></div>
        <div class="relative w-full max-w-lg rounded-xl bg-white p-6 shadow-xl"><h2 id="document-confirm-title" class="text-lg font-bold text-content" x-text="confirm.title"></h2><p class="mt-3 whitespace-pre-line text-sm leading-6 text-content-muted" x-text="confirm.message"></p><p x-show="error" x-text="error" class="mt-3 text-sm text-error"></p><div class="mt-6 flex justify-end gap-3"><button type="button" class="btn-ghost" @click="closeConfirm()">Annulla</button><button type="button" :class="confirm.danger ? 'btn-danger' : 'btn-brand'" :disabled="busy" @click="executeWorkflow()"><span x-text="busy ? 'Operazione in corso...' : confirm.submit"></span></button></div></div>
    </div>
</div>

<script data-navigate-once>
    window.documentActionCenter ??= function (config) {
        return {
            ...config,
            selectedDocument: null,
            busy: false,
            error: '',
            emailOpen: false,
            emailLoading: false,
            paymentOpen: false,
            confirmOpen: false,
            notice: { show: false, message: '', type: 'success', timeout: null },
            editingPaymentId: null,
            email: { recipientEmail: '', cc: '', bcc: '', subject: '', body: '', attachPdf: true },
            payment: { amount: '', paidAt: '', reference: '', notes: '', bankName: '' },
            confirm: { action: '', title: '', message: '', submit: '', danger: false },
            handleAction(detail) {
                const document = this.documents.find((item) => item.id === Number(detail.id));
                if (!document || this.busy) return;

                this.selectedDocument = document;
                this.error = '';
                if (detail.action === 'email') this.openEmail();
                if (detail.action === 'payment') this.openPayment();
                if (detail.action === 'validate-xml') this.openConfirm('validate-xml');
                if (detail.action === 'send-sdi') this.openConfirm('send-sdi');
            },
            documentLabel() {
                return this.selectedDocument?.number ?? (this.selectedDocument ? `#${this.selectedDocument.id}` : '');
            },
            csrf() {
                return document.querySelector('meta[name="csrf-token"]')?.content ?? '';
            },
            async request(url, options = {}) {
                const response = await fetch(url, {
                    ...options,
                    headers: { Accept: 'application/json', 'X-CSRF-TOKEN': this.csrf(), ...(options.headers ?? {}) },
                });
                const data = await response.json().catch(() => ({}));
                if (!response.ok || data.success === false) {
                    const errors = Object.values(data.errors ?? {}).flat().filter(Boolean);
                    throw new Error(errors[0] ?? data.error ?? 'Operazione non riuscita.');
                }
                return data;
            },
            notify(message, type = 'success') {
                window.clearTimeout(this.notice.timeout);
                this.notice = { show: true, message, type, timeout: window.setTimeout(() => { this.notice.show = false; }, 4500) };
            },
            async openEmail() {
                this.emailOpen = true;
                this.emailLoading = true;
                try {
                    const data = await this.request(`${this.base}/${this.selectedDocument.id}/email-preview`);
                    const preview = data.preview ?? {};
                    this.email = { recipientEmail: preview.recipient_email ?? '', cc: preview.cc ?? '', bcc: preview.bcc ?? '', subject: preview.subject ?? '', body: preview.body ?? '', attachPdf: preview.attach_pdf ?? true };
                } catch (error) {
                    this.closeEmail();
                    this.notify(error.message, 'error');
                } finally {
                    this.emailLoading = false;
                }
            },
            closeEmail() { if (!this.busy) { this.emailOpen = false; this.error = ''; } },
            async submitEmail() {
                this.busy = true;
                this.error = '';
                try {
                    const data = await this.request(`${this.base}/${this.selectedDocument.id}/send-email`, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ recipient_email: this.email.recipientEmail, cc: this.email.cc || null, bcc: this.email.bcc || null, subject: this.email.subject || null, body: this.email.body || null, attach_pdf: this.email.attachPdf }) });
                    this.emailOpen = false;
                    this.notify(data.message ?? 'Email accodata correttamente.');
                } catch (error) { this.error = error.message; } finally { this.busy = false; }
            },
            openPayment() { this.paymentOpen = true; this.resetPaymentForm(); },
            closePayment() { if (!this.busy) { this.paymentOpen = false; this.error = ''; this.resetPaymentForm(); } },
            paymentCopy() {
                const receivable = this.type === 'sales';
                const action = receivable ? 'incasso' : 'pagamento';
                return { action, title: receivable ? 'Registra incasso' : 'Registra pagamento', newTitle: receivable ? 'Nuovo incasso' : 'Nuovo pagamento', editTitle: receivable ? 'Modifica incasso' : 'Modifica pagamento', saveLabel: receivable ? 'Salva incasso' : 'Salva pagamento', updateLabel: receivable ? 'Aggiorna incasso' : 'Aggiorna pagamento', dateLabel: receivable ? 'Data accredito (opzionale)' : 'Data pagamento (opzionale)', referenceLabel: receivable ? 'Rif. accredito (opzionale)' : 'Rif. pagamento (opzionale)', notesLabel: receivable ? 'Causale accredito (opzionale)' : 'Causale pagamento (opzionale)', bankLabel: receivable ? 'Banca accredito (opzionale)' : 'Banca addebito (opzionale)', totalPaidLabel: receivable ? 'Totale incassato' : 'Totale pagato', remainingLabel: receivable ? 'Residuo da incassare' : 'Residuo da pagare', listTitle: receivable ? 'Incassi registrati' : 'Pagamenti registrati', emptyListLabel: receivable ? 'Nessun incasso registrato.' : 'Nessun pagamento registrato.' };
            },
            remainingBalance() { return Math.max(0, (this.selectedDocument?.netDue ?? 0) - (this.selectedDocument?.totalPaid ?? 0)); },
            setQuickPayment(fraction) { this.payment.amount = (Math.max(1, Math.round(this.remainingBalance() * fraction)) / 100).toFixed(2); },
            resetPaymentForm() { this.editingPaymentId = null; this.payment = { amount: '', paidAt: '', reference: '', notes: '', bankName: '' }; },
            editPayment(entry) { this.editingPaymentId = entry.id; this.payment = { amount: (entry.amount / 100).toFixed(2), paidAt: entry.paidAt ?? '', reference: entry.reference ?? '', notes: entry.notes ?? '', bankName: entry.bankName ?? '' }; },
            async savePayment() {
                const amount = Number.parseFloat(this.payment.amount);
                if (!Number.isFinite(amount) || amount <= 0) { this.error = 'Inserisci un importo valido maggiore di zero.'; return; }
                this.busy = true; this.error = '';
                try {
                    const updating = Boolean(this.editingPaymentId);
                    const data = await this.request(`${this.base}/${this.selectedDocument.id}/payments${updating ? `/${this.editingPaymentId}` : ''}`, { method: updating ? 'PUT' : 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ amount, paid_at: this.payment.paidAt || null, reference: this.payment.reference || null, notes: this.payment.notes || null, bank_name: this.payment.bankName || null }) });
                    this.applyPaymentResponse(data);
                    this.notify(updating ? `${this.capitalize(this.paymentCopy().action)} aggiornato.` : `${this.capitalize(this.paymentCopy().action)} registrato.`);
                    this.resetPaymentForm();
                } catch (error) { this.error = error.message; } finally { this.busy = false; }
            },
            async deletePayment(entry) {
                if (!window.confirm(`Eliminare questo ${this.paymentCopy().action}?`)) return;
                this.busy = true; this.error = '';
                try {
                    const data = await this.request(`${this.base}/${this.selectedDocument.id}/payments/${entry.id}`, { method: 'DELETE' });
                    this.applyPaymentResponse(data);
                    if (this.editingPaymentId === entry.id) this.resetPaymentForm();
                    this.notify(`${this.capitalize(this.paymentCopy().action)} eliminato.`);
                } catch (error) { this.error = error.message; } finally { this.busy = false; }
            },
            applyPaymentResponse(data) {
                this.selectedDocument.totalPaid = data.total_paid;
                this.selectedDocument.paymentStatus = data.payment_status;
                this.selectedDocument.payments = (data.payments ?? []).map((entry) => ({ id: entry.id, amount: entry.amount, paidAt: entry.paid_at, reference: entry.reference, notes: entry.notes, bankName: entry.bank_name }));
            },
            openConfirm(action) {
                const sending = action === 'send-sdi';
                this.confirm = { action, title: sending ? 'Conferma invio SDI' : 'Conferma validazione XML', submit: sending ? 'Invia a SDI' : 'Verifica XML', danger: sending, message: sending ? `Stai per inviare allo SDI ${this.documentLabel()}.\n\nQuesta azione è irreversibile. Dopo l'invio non potrai più modificarla.\n\nControlla prima di confermare:\n- Anagrafica cliente\n- Importi e aliquote IVA\n- Codice destinatario o PEC` : `Confermi la validazione XML di ${this.documentLabel()}?` };
                this.confirmOpen = true;
            },
            closeConfirm() { if (!this.busy) { this.confirmOpen = false; this.error = ''; } },
            async executeWorkflow() {
                this.busy = true; this.error = '';
                try {
                    const endpoint = this.confirm.action === 'send-sdi' ? 'send-sdi' : 'validate-xml';
                    const data = await this.request(`${this.base}/${this.selectedDocument.id}/${endpoint}`, { method: 'POST' });
                    this.selectedDocument.status = data.document.status;
                    this.selectedDocument.sdiEditable = data.document.is_sdi_editable;
                    this.confirmOpen = false;
                    this.notify(data.message);
                    await this.$wire.$refresh();
                } catch (error) { this.error = error.message; } finally { this.busy = false; }
            },
            money(value) { return new Intl.NumberFormat('it-IT', { style: 'currency', currency: 'EUR' }).format((value ?? 0) / 100); },
            formatDate(value) { return value ? new Intl.DateTimeFormat('it-IT').format(new Date(`${value}T00:00:00`)) : 'Data non indicata'; },
            capitalize(value) { return value ? value.charAt(0).toUpperCase() + value.slice(1) : ''; },
        };
    };
</script>
