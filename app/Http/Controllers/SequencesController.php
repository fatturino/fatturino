<?php

namespace App\Http\Controllers;

use App\Models\Sequence;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class SequencesController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Sequences/Index', [
            'sequences' => Sequence::orderBy('name')->paginate(10),
            'typeOptions' => $this->typeOptions(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => [
                'required',
                Rule::unique('sequences', 'name')->where('type', $request->type),
            ],
            'type' => 'required|string',
            'pattern' => 'required|string',
        ]);

        Sequence::create($validated);

        return redirect()->route('sequences.index');
    }

    public function update(Request $request, Sequence $sequence): RedirectResponse
    {
        // System sequences cannot change type
        if ($sequence->is_system) {
            $request->merge(['type' => $sequence->type]);
        }

        $validated = $request->validate([
            'name' => [
                'required',
                Rule::unique('sequences', 'name')
                    ->where('type', $request->type)
                    ->ignore($sequence->id),
            ],
            'type' => 'required|string',
            'pattern' => 'required|string',
        ]);

        $sequence->update($validated);

        return redirect()->route('sequences.index');
    }

    public function destroy(Sequence $sequence): RedirectResponse
    {
        try {
            $sequence->delete();
        } catch (\Exception $e) {
            return back()->withErrors(['sequence' => $e->getMessage()]);
        }

        return redirect()->route('sequences.index');
    }

    private function typeOptions(): array
    {
        return [
            ['value' => 'electronic_invoice', 'label' => __('app.sequences.type_electronic_invoice')],
            ['value' => 'purchase', 'label' => __('app.sequences.type_purchase')],
            ['value' => 'self_invoice', 'label' => __('app.sequences.type_self_invoice')],
            ['value' => 'proforma', 'label' => __('app.sequences.type_proforma')],
            ['value' => 'credit_note', 'label' => __('app.sequences.type_credit_note')],
            ['value' => 'quote', 'label' => __('app.sequences.type_quote')],
        ];
    }
}
