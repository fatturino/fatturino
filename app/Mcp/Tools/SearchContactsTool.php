<?php

namespace App\Mcp\Tools;

use App\Models\Contact;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Search contacts (customers and suppliers) on this Fatturino instance by name, VAT number, tax code, or email. Use the returned id as contact_id when creating a self-invoice.')]
class SearchContactsTool extends Tool
{
    public function handle(Request $request): Response|ResponseFactory
    {
        if ($request->user() === null) {
            return Response::error('Authentication required.');
        }

        $validated = $request->validate([
            'query' => ['required', 'string', 'min:1', 'max:100'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $query = trim((string) $validated['query']);
        $limit = (int) ($validated['limit'] ?? 20);

        $like = '%'.$query.'%';
        $contacts = Contact::query()
            ->where(function ($builder) use ($like): void {
                $builder->where('name', 'like', $like)
                    ->orWhere('vat_number', 'like', $like)
                    ->orWhere('tax_code', 'like', $like)
                    ->orWhere('email', 'like', $like);
            })
            ->orderBy('name')
            ->limit($limit)
            ->get(['id', 'name', 'vat_number', 'tax_code', 'email', 'country']);

        return Response::structured([
            'contacts' => $contacts->map(fn (Contact $contact): array => [
                'id' => $contact->id,
                'name' => $contact->name,
                'vat_number' => $contact->vat_number,
                'tax_code' => $contact->tax_code,
                'email' => $contact->email,
                'country' => $contact->country,
            ])->all(),
        ]);
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'query' => $schema->string()
                ->description('Search text matched against name, VAT number, tax code, or email.')
                ->required(),
            'limit' => $schema->integer()
                ->description('Maximum number of contacts to return (1-50, default 20).'),
        ];
    }
}
