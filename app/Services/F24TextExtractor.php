<?php

namespace App\Services;

use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Local best-effort text extraction for F24 PDFs and images.
 * Uses pdftotext / pdftoppm / tesseract when available; never throws for missing tools.
 */
class F24TextExtractor
{
    private const MIN_USEFUL_TEXT_LENGTH = 40;

    /**
     * @return array{text: string, method: string, warning: ?string, raw: array<string, mixed>}
     */
    public function extract(string $absolutePath, string $mime): array
    {
        $raw = [
            'mime' => $mime,
            'path_basename' => basename($absolutePath),
            'tools' => $this->availableTools(),
        ];

        try {
            if (str_starts_with($mime, 'image/')) {
                return $this->extractFromImage($absolutePath, $raw);
            }

            if ($mime === 'application/pdf' || Str::endsWith(strtolower($absolutePath), '.pdf')) {
                return $this->extractFromPdf($absolutePath, $raw);
            }

            return [
                'text' => '',
                'method' => 'unsupported',
                'warning' => __('app.f24.ocr_unsupported_type'),
                'raw' => $raw,
            ];
        } catch (Throwable $e) {
            report($e);

            return [
                'text' => '',
                'method' => 'failed',
                'warning' => __('app.f24.ocr_failed'),
                'raw' => $raw + ['error' => $e->getMessage()],
            ];
        }
    }

    /**
     * @param  array<string, mixed>  $raw
     * @return array{text: string, method: string, warning: ?string, raw: array<string, mixed>}
     */
    private function extractFromPdf(string $absolutePath, array $raw): array
    {
        if (! $this->commandExists('pdftotext')) {
            return [
                'text' => '',
                'method' => 'missing_tools',
                'warning' => __('app.f24.ocr_tools_missing'),
                'raw' => $raw,
            ];
        }

        $text = $this->run(['pdftotext', '-layout', '-enc', 'UTF-8', $absolutePath, '-']);
        $raw['pdftotext_length'] = strlen($text);

        if (strlen(trim($text)) >= self::MIN_USEFUL_TEXT_LENGTH) {
            return [
                'text' => $text,
                'method' => 'pdftotext',
                'warning' => null,
                'raw' => $raw,
            ];
        }

        if (! $this->commandExists('pdftoppm') || ! $this->commandExists('tesseract')) {
            return [
                'text' => $text,
                'method' => 'pdftotext_sparse',
                'warning' => __('app.f24.ocr_partial'),
                'raw' => $raw,
            ];
        }

        $tmpDir = sys_get_temp_dir().'/fatturino-f24-'.Str::uuid();
        mkdir($tmpDir, 0700, true);

        try {
            $this->run(['pdftoppm', '-png', '-r', '300', $absolutePath, $tmpDir.'/page']);
            $pages = glob($tmpDir.'/page*.png') ?: [];
            sort($pages);
            $chunks = [];
            foreach ($pages as $page) {
                $chunks[] = $this->runTesseract($page);
            }
            $ocrText = trim(implode("\n", $chunks));
            $raw['ocr_pages'] = count($pages);
            $raw['ocr_length'] = strlen($ocrText);

            if ($ocrText === '') {
                return [
                    'text' => $text,
                    'method' => 'tesseract_empty',
                    'warning' => __('app.f24.ocr_partial'),
                    'raw' => $raw,
                ];
            }

            return [
                'text' => $ocrText,
                'method' => 'pdftoppm_tesseract',
                'warning' => null,
                'raw' => $raw,
            ];
        } finally {
            foreach (glob($tmpDir.'/*') ?: [] as $file) {
                @unlink($file);
            }
            @rmdir($tmpDir);
        }
    }

    /**
     * @param  array<string, mixed>  $raw
     * @return array{text: string, method: string, warning: ?string, raw: array<string, mixed>}
     */
    private function extractFromImage(string $absolutePath, array $raw): array
    {
        if (! $this->commandExists('tesseract')) {
            return [
                'text' => '',
                'method' => 'missing_tools',
                'warning' => __('app.f24.ocr_tools_missing'),
                'raw' => $raw,
            ];
        }

        $text = $this->runTesseract($absolutePath);
        $raw['ocr_length'] = strlen($text);

        return [
            'text' => $text,
            'method' => 'tesseract',
            'warning' => $text === '' ? __('app.f24.ocr_partial') : null,
            'raw' => $raw,
        ];
    }

    private function runTesseract(string $imagePath): string
    {
        $lang = $this->tesseractLanguage();

        return trim($this->run(['tesseract', $imagePath, 'stdout', '-l', $lang, '--psm', '6']));
    }

    private function tesseractLanguage(): string
    {
        try {
            $langs = $this->run(['tesseract', '--list-langs']);
            if (str_contains($langs, 'ita')) {
                return 'ita';
            }
        } catch (Throwable) {
            // fall through
        }

        return 'eng';
    }

    /**
     * @param  list<string>  $command
     */
    private function run(array $command): string
    {
        $result = Process::timeout(120)->run($command);

        if ($result->failed()) {
            throw new RuntimeException(trim($result->errorOutput() ?: $result->output()) ?: 'Command failed: '.$command[0]);
        }

        return $result->output();
    }

    private function commandExists(string $binary): bool
    {
        $result = Process::run(['sh', '-c', 'command -v '.escapeshellarg($binary)]);

        return $result->successful() && trim($result->output()) !== '';
    }

    /**
     * @return array{pdftotext: bool, pdftoppm: bool, tesseract: bool}
     */
    public function availableTools(): array
    {
        return [
            'pdftotext' => $this->commandExists('pdftotext'),
            'pdftoppm' => $this->commandExists('pdftoppm'),
            'tesseract' => $this->commandExists('tesseract'),
        ];
    }
}
