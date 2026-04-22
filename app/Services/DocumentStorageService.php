<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;

class DocumentStorageService
{
    // All documents stored under this root on the local (private) disk
    private const BASE_PATH = 'documents';

    /**
     * Store raw XML content and return the relative path.
     *
     * @param  string  $category  e.g. 'sales', 'purchase', 'credit-notes', 'self-invoices'
     */
    public function storeXml(string $xmlContent, string $category, int $year, string $filename): string
    {
        $path = self::BASE_PATH . "/xml/{$category}/{$year}/{$filename}";

        Storage::disk('local')->put($path, $xmlContent);

        return $path;
    }

    /**
     * Store raw PDF binary content and return the relative path.
     *
     * @param  string  $category  e.g. 'sales', 'credit-notes'
     */
    public function storePdf(string $pdfContent, string $category, int $year, string $filename): string
    {
        $path = self::BASE_PATH . "/pdf/{$category}/{$year}/{$filename}";

        Storage::disk('local')->put($path, $pdfContent);

        return $path;
    }

    /**
     * Read a stored XML file. Returns null if not found.
     */
    public function getXml(string $path): ?string
    {
        if (! Storage::disk('local')->exists($path)) {
            return null;
        }

        return Storage::disk('local')->get($path);
    }

    /**
     * Read a stored PDF file. Returns null if not found.
     */
    public function getPdf(string $path): ?string
    {
        if (! Storage::disk('local')->exists($path)) {
            return null;
        }

        return Storage::disk('local')->get($path);
    }

    /**
     * Check whether a document file exists on disk.
     */
    public function exists(string $path): bool
    {
        return Storage::disk('local')->exists($path);
    }
}
