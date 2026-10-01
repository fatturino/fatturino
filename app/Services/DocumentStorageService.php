<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use RuntimeException;

class DocumentStorageService
{
    private const DISK = 'documents';

    /**
     * Store raw XML content and return the relative path.
     *
     * @param  string  $category  e.g. 'sales', 'purchase', 'credit-notes', 'self-invoices'
     */
    public function storeXml(string $xmlContent, string $category, int $year, string $filename): string
    {
        $path = $this->path("xml/{$category}/{$year}/{$filename}");

        $this->storeImmutable($path, $xmlContent);

        return $path;
    }

    /**
     * Store raw PDF binary content and return the relative path.
     *
     * @param  string  $category  e.g. 'sales', 'credit-notes'
     */
    public function storePdf(string $pdfContent, string $category, int $year, string $filename): string
    {
        $path = $this->path("pdf/{$category}/{$year}/{$filename}");

        $this->storeImmutable($path, $pdfContent);

        return $path;
    }

    /**
     * Read a stored XML file. Returns null if not found.
     */
    public function getXml(string $path): ?string
    {
        return $this->get($path);
    }

    /**
     * Read a stored PDF file. Returns null if not found.
     */
    public function getPdf(string $path): ?string
    {
        return $this->get($path);
    }

    /**
     * Check whether a document file exists on disk.
     */
    public function exists(string $path): bool
    {
        return Storage::disk(self::DISK)->exists($path);
    }

    /**
     * Copy a legacy local snapshot to the configured document disk without
     * changing its database path. Existing identical snapshots are a no-op.
     */
    public function migrateFromLocal(string $path): string
    {
        $local = Storage::disk('local');
        if (! $local->exists($path)) {
            throw new RuntimeException("Legacy document snapshot is missing: {$path}");
        }

        $targetPath = $this->path(ltrim($path, '/'));
        $this->storeImmutable($targetPath, $local->get($path));

        return $targetPath;
    }

    private function get(string $path): ?string
    {
        $disk = Storage::disk(self::DISK);

        return $disk->exists($path) ? $disk->get($path) : null;
    }

    private function storeImmutable(string $path, string $contents): void
    {
        $disk = Storage::disk(self::DISK);

        if ($disk->exists($path)) {
            $existing = $disk->get($path);
            if (hash_equals(hash('sha256', $existing), hash('sha256', $contents))) {
                return;
            }

            throw new RuntimeException("Refusing to overwrite immutable document snapshot: {$path}");
        }

        if ($disk->put($path, $contents) !== true || ! $disk->exists($path)) {
            throw new RuntimeException("Unable to persist document snapshot: {$path}");
        }

        $persisted = $disk->get($path);
        if (! hash_equals(hash('sha256', $contents), hash('sha256', $persisted))) {
            throw new RuntimeException("Document snapshot integrity check failed: {$path}");
        }
    }

    private function path(string $path): string
    {
        $path = ltrim($path, '/');

        if (config('filesystems.disks.'.self::DISK.'.driver') === 's3') {
            return preg_replace('#^documents/#', '', $path);
        }

        return str_starts_with($path, 'documents/') ? $path : 'documents/'.$path;
    }
}
