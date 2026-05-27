<?php

namespace App\Console\Commands;

use App\Services\OpenApiSdiService;
use App\Settings\CompanySettings;
use App\Settings\OpenApiSettings;
use Illuminate\Console\Command;

class CheckActivationCommand extends Command
{
    protected $signature = 'openapi:check-status
        {fiscal_id? : Partita IVA da verificare (default: quella configurata in Impostazioni Aziendali)}';

    protected $description = 'Verifica lo stato di attivazione del servizio OpenAPI SDI per una Partita IVA';

    public function handle(OpenApiSettings $settings, CompanySettings $companySettings): int
    {
        $fiscalId = $this->argument('fiscal_id')
            ?? $companySettings->company_vat_number;

        if (empty($fiscalId)) {
            $this->error('Nessuna Partita IVA specificata e nessuna configurata nelle Impostazioni Aziendali.');

            return self::FAILURE;
        }

        $this->info("Verifica stato attivazione per Partita IVA: {$fiscalId}");

        $service = new OpenApiSdiService($settings);

        if (! $service->isConfigured()) {
            $this->error('OpenAPI SDI non configurato. Imposta l\'API token nelle impostazioni del plugin.');

            return self::FAILURE;
        }

        $this->line('Ambiente: '.($settings->sandbox ? 'Sandbox' : 'Produzione'));
        $this->line('');

        $result = $service->checkActivationStatus($fiscalId);

        if ($result['activated']) {
            $this->line('✅ <fg=green>Servizio ATTIVO</>');

            if (isset($result['data'])) {
                $this->table(['Campo', 'Valore'], $this->flattenData($result['data']));
            }
        } elseif (isset($result['registration_required']) && $result['registration_required']) {
            $this->line('⚠️  <fg=yellow>Partita IVA non registrata su OpenAPI</>');
            $this->line('   Usa il pannello impostazioni per registrarla.');
        } else {
            $this->line('❌ <fg=red>Errore</>');
            $this->line('   '.($result['message'] ?? 'Errore sconosciuto'));
        }

        return self::SUCCESS;
    }

    /**
     * Flatten nested data array into key-value rows for table display.
     */
    private function flattenData(array $data, string $prefix = ''): array
    {
        $rows = [];

        foreach ($data as $key => $value) {
            $label = $prefix ? "{$prefix}.{$key}" : $key;

            if (is_array($value) && ! empty($value) && array_keys($value) !== range(0, count($value) - 1)) {
                $rows = array_merge($rows, $this->flattenData($value, $label));
            } else {
                $rows[] = [
                    'Campo' => $label,
                    'Valore' => is_bool($value) ? ($value ? 'true' : 'false') : (is_scalar($value) ? (string) $value : json_encode($value)),
                ];
            }
        }

        return $rows;
    }
}
