<?php

namespace App\Mcp\Tools;

use App\Actions\SaveSelfInvoice;
use App\Models\FiscalDocument;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;

#[Description('Create a draft self-invoice (autofattura). Does not validate XML or send to SDI. Supported document types: TD17, TD18, TD19, TD28, TD29. Amounts in unit_price are euros.')]
#[IsIdempotent(false)]
class CreateSelfInvoiceTool extends Tool
{
    public function __construct(
        private readonly SaveSelfInvoice $saveSelfInvoice,
    ) {}

    public function handle(Request $request): Response|ResponseFactory
    {
        if ($request->user() === null) {
            return Response::error('Authentication required.');
        }

        try {
            $validated = $request->validate([
                'contact_id' => ['required', 'integer', 'exists:contacts,id'],
                'date' => ['required', 'date'],
                'due_date' => ['nullable', 'date'],
                'document_type' => ['required', 'string', Rule::in(['TD17', 'TD18', 'TD19', 'TD28', 'TD29'])],
                'related_invoice_number' => ['required', 'string', 'max:20'],
                'related_invoice_date' => ['required', 'date'],
                'notes' => ['nullable', 'string'],
                'lines' => ['required', 'array', 'min:1'],
                'lines.*.description' => ['required', 'string'],
                'lines.*.quantity' => ['required', 'numeric', 'min:0.01'],
                'lines.*.unit_price' => ['required', 'numeric', 'min:0'],
                'lines.*.vat_rate' => ['required', 'string'],
                'lines.*.unit_of_measure' => ['nullable', 'string'],
            ]);

            $invoice = $this->saveSelfInvoice->create($validated);
        } catch (ValidationException $exception) {
            return Response::error(collect($exception->errors())->flatten()->implode(' '));
        }

        return Response::structured($this->serializeInvoice($invoice));
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'contact_id' => $schema->integer()
                ->description('Supplier contact id from search_contacts.')
                ->required(),
            'date' => $schema->string()
                ->description('Self-invoice issue date (YYYY-MM-DD).')
                ->required(),
            'due_date' => $schema->string()
                ->description('Optional due date (YYYY-MM-DD).'),
            'document_type' => $schema->string()
                ->description('FatturaPA type: TD17, TD18, TD19, TD28, or TD29.')
                ->enum(['TD17', 'TD18', 'TD19', 'TD28', 'TD29'])
                ->required(),
            'related_invoice_number' => $schema->string()
                ->description('Supplier invoice number being integrated.')
                ->required(),
            'related_invoice_date' => $schema->string()
                ->description('Supplier invoice date (YYYY-MM-DD).')
                ->required(),
            'notes' => $schema->string()
                ->description('Optional notes.'),
            'lines' => $schema->array()
                ->description('Invoice lines. unit_price is in euros.')
                ->items($schema->object([
                    'description' => $schema->string()->required(),
                    'quantity' => $schema->number()->required(),
                    'unit_price' => $schema->number()->required(),
                    'vat_rate' => $schema->string()->description('VAT rate code, e.g. R22.')->required(),
                    'unit_of_measure' => $schema->string(),
                ]))
                ->min(1)
                ->required(),
        ];
    }

    /** @return array<string, mixed> */
    private function serializeInvoice(FiscalDocument $invoice): array
    {
        return [
            'id' => $invoice->id,
            'number' => $invoice->number,
            'status' => $invoice->status?->value ?? (string) $invoice->status,
            'type' => $invoice->type,
            'document_type' => $invoice->document_type,
            'date' => $invoice->date?->toDateString(),
            'contact_id' => $invoice->contact_id,
            'related_invoice_number' => $invoice->related_invoice_number,
            'related_invoice_date' => $invoice->related_invoice_date?->toDateString(),
            'total_net' => $invoice->total_net,
            'total_vat' => $invoice->total_vat,
            'total_gross' => $invoice->total_gross,
            'sdi_sent' => false,
        ];
    }
}
