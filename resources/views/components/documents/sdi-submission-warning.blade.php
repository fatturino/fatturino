@props(['submission' => null])

@if($submission)
    <section class="rounded-lg border border-warning/30 bg-warning-bg p-4 text-sm text-warning" role="alert" aria-labelledby="sdi-submission-warning-title">
        <div class="flex items-start gap-3">
            <x-icon name="o-exclamation-triangle" class="mt-0.5 size-5 shrink-0" aria-hidden="true" />
            <div>
                <h2 id="sdi-submission-warning-title" class="font-semibold">Invio SDI da verificare</h2>
                <p class="mt-1">L’esito dell’invio non è certo. Non reinviare il documento. Verifica la situazione prima di procedere.</p>
                @if($submission->support_message)
                    <p class="mt-2 text-xs text-warning/90">{{ $submission->support_message }}</p>
                @endif
                <x-app-link href="/advanced" class="mt-3 inline-flex rounded font-semibold underline underline-offset-4 focus:outline-none focus:ring-2 focus:ring-warning/40">Apri diagnostica SDI</x-app-link>
            </div>
        </div>
    </section>
@endif
