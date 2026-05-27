<?php

namespace App\Services;

use App\Contracts\SdiProvider;
use Illuminate\Http\Response as HttpResponse;

class XmlWorkflowService
{
    public function __construct(
        protected LocalXmlValidator $localValidator,
        protected SdiProvider $sdiProvider
    ) {}

    /**
     * Build a download response for an XML document.
     */
    public function downloadResponse(string $xmlContent, string $fileName): HttpResponse
    {
        return response($xmlContent, 200, [
            'Content-Type' => 'application/xml; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$fileName.'"',
        ]);
    }

    /**
     * Run local structural validation and provider-side validation.
     *
     * @return array{valid: bool, errors?: string[]}
     */
    public function validate(string $xmlContent): array
    {
        $localResult = $this->localValidator->validate($xmlContent);
        if (! $localResult['valid']) {
            return [
                'valid' => false,
                'errors' => $localResult['errors'],
            ];
        }

        $providerResult = $this->sdiProvider->validateXml($xmlContent);
        if (! ($providerResult['valid'] ?? false)) {
            return [
                'valid' => false,
                'errors' => $providerResult['errors'] ?? ['Validazione provider fallita.'],
            ];
        }

        return ['valid' => true];
    }

    public function send(string $xmlContent, string $fileName): array
    {
        return $this->sdiProvider->sendInvoice($xmlContent, $fileName);
    }

    public function providerId(): string
    {
        return $this->sdiProvider->id();
    }
}
