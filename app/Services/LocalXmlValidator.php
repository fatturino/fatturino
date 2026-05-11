<?php

namespace App\Services;

use SimpleXMLElement;

/**
 * Performs local structural validation of FatturaPA XML.
 *
 * Checks for required elements and data before the XML is sent
 * to the SDI provider for remote validation.
 */
class LocalXmlValidator
{
    private const FATTURA_NS = 'http://ivaservizi.agenziaentrate.gov.it/docs/xsd/fatture/v1.2';

    /**
     * Validate FatturaPA XML structure.
     *
     * @return array{valid: bool, errors: string[]}
     */
    public function validate(string $xmlContent): array
    {
        $errors = [];

        // Suppress XML parsing errors — we handle them explicitly
        libxml_use_internal_errors(true);

        try {
            $xml = new SimpleXMLElement($xmlContent);
        } catch (\Exception $e) {
            $errors[] = __('app.invoices.xml_errors.parsing_error', ['error' => $e->getMessage()]);
            libxml_clear_errors();

            return ['valid' => false, 'errors' => $errors];
        }

        $ns = self::FATTURA_NS;
        $xml->registerXPathNamespace('p', $ns);

        // Check FatturaElettronicaHeader
        $header = $xml->xpath('/p:FatturaElettronica/FatturaElettronicaHeader');
        if (empty($header)) {
            $errors[] = __('app.invoices.xml_errors.missing_header');
        }

        // Check FatturaElettronicaBody (at least one)
        $bodies = $xml->xpath('/p:FatturaElettronica/FatturaElettronicaBody');
        if (empty($bodies)) {
            $errors[] = __('app.invoices.xml_errors.missing_body');
        }

        // Check DatiTrasmissione
        $datiTrasmissione = $xml->xpath('/p:FatturaElettronica/FatturaElettronicaHeader/DatiTrasmissione');
        if (empty($datiTrasmissione)) {
            $errors[] = __('app.invoices.xml_errors.missing_transmission_data');
        } else {
            $dt = $datiTrasmissione[0];

            // IdTrasmittente
            $idTrasmittente = $dt->xpath('IdTrasmittente');
            if (empty($idTrasmittente)) {
                $errors[] = __('app.invoices.xml_errors.missing_sender_id');
            }

            // ProgressivoInvio
            $progressivo = $dt->xpath('ProgressivoInvio');
            if (empty($progressivo) || (string) $progressivo[0] === '') {
                $errors[] = __('app.invoices.xml_errors.missing_progressive');
            }

            // FormatoTrasmissione
            $formato = $dt->xpath('FormatoTrasmissione');
            if (empty($formato) || (string) $formato[0] === '') {
                $errors[] = __('app.invoices.xml_errors.missing_format');
            }

            // CodiceDestinatario or PEC (at least one required)
            $codiceDestinatario = $dt->xpath('CodiceDestinatario');
            $pec = $dt->xpath('PECDestinatario');
            $hasRecipient = false;

            if (! empty($codiceDestinatario) && (string) $codiceDestinatario[0] !== '') {
                $hasRecipient = true;
            }
            if (! empty($pec) && (string) $pec[0] !== '') {
                $hasRecipient = true;
            }
            if (! $hasRecipient) {
                $errors[] = __('app.invoices.xml_errors.missing_recipient');
            }
        }

        // Check customer fiscal ID in the first body
        if (! empty($bodies)) {
            $body = $bodies[0];
            $idFiscaleIva = $body->xpath('DatiGenerali/DatiGeneraliDocumento/DatiCessionarioCommittente/DatiAnagrafici/IdFiscaleIVA');
            $codiceFiscale = $body->xpath('DatiGenerali/DatiGeneraliDocumento/DatiCessionarioCommittente/DatiAnagrafici/CodiceFiscale');

            $hasCustomerFiscalId = false;

            if (! empty($idFiscaleIva)) {
                $idPaese = (string) ($idFiscaleIva[0]->IdPaese ?? '');
                $idCodice = (string) ($idFiscaleIva[0]->IdCodice ?? '');
                $len = strlen($idCodice);
                if ($idPaese === 'IT' && $len >= 11 && $len <= 16) {
                    $hasCustomerFiscalId = true;
                }
                if ($idPaese !== 'IT' && $idCodice !== '') {
                    $hasCustomerFiscalId = true;
                }
            }

            if (! $hasCustomerFiscalId && ! empty($codiceFiscale) && (string) $codiceFiscale[0] !== '') {
                $cf = (string) $codiceFiscale[0];
                $len = strlen($cf);
                if ($len >= 11 && $len <= 16) {
                    $hasCustomerFiscalId = true;
                }
            }

            if (! $hasCustomerFiscalId) {
                $errors[] = __('app.invoices.xml_errors.missing_customer_fiscal_id');
            }
        }

        libxml_clear_errors();

        return [
            'valid' => empty($errors),
            'errors' => $errors,
        ];
    }
}
