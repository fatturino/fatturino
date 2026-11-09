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
    public function storeXml(string $xmlContent, string $category, int $year, string $publicId, string $filename): string
    {
        $path = $this->snapshotPath('xml', $category, $year, $publicId, $filename);

        $this->storeImmutable($path, $xmlContent);

        return $path;
    }

    /**
     * Store raw PDF binary content and return the relative path.
     *
     * @param  string  $category  e.g. 'sales', 'credit-notes'
     */
    public function storePdf(string $pdfContent, string $category, int $year, string $publicId, string $filename): string
    {
        $path = $this->snapshotPath('pdf', $category, $year, $publicId, $filename);

        $this->storeImmutable($path, $pdfContent);

        return $path;
    }

    /**
     * Store an F24 source attachment under documents/f24/{year}/{id}/{file}.
     */
    public function storeF24Attachment(string $content, int $year, string $publicId, string $filename): string
    {
        $path = $this->path(implode('/', [
            'f24',
            (string) $year,
            trim($publicId, '/'),
            ltrim($filename, '/'),
        ]));

        $this->storeImmutable($path, $content);

        return $path;
    }

    /**
     * Absolute filesystem path for a documents-disk file (local driver only).
     */
    public function absolutePath(string $relativePath): ?string
    {
        $disk = Storage::disk(self::DISK);

        if (! $disk->exists($relativePath)) {
            return null;
        }

        if (config('filesystems.disks.'.self::DISK.'.driver') !== 'local') {
            return null;
        }

        return $disk->path($relativePath);
    }

    /**
     * Run a callback with a local filesystem path for the given documents-disk object.
     * On S3 (or any non-local driver) the file is copied to a temp path and removed afterwards.
     *
     * @template T
     *
     * @param  callable(string $absolutePath): T  $callback
     * @return T|null
     */
    public function withLocalPath(string $relativePath, callable $callback): mixed
    {
        $absolute = $this->absolutePath($relativePath);
        if ($absolute !== null) {
            return $callback($absolute);
        }

        $contents = $this->getContents($relativePath);
        if ($contents === null) {
            return null;
        }

        $extension = pathinfo($relativePath, PATHINFO_EXTENSION);
        $tmpPath = sys_get_temp_dir().'/fatturino-doc-'.bin2hex(random_bytes(8))
            .($extension !== '' ? '.'.$extension : '');

        try {
            if (file_put_contents($tmpPath, $contents) === false) {
                throw new RuntimeException("Unable to materialize document snapshot locally: {$relativePath}");
            }

            return $callback($tmpPath);
        } finally {
            if (is_file($tmpPath)) {
                @unlink($tmpPath);
            }
        }
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
     * Read any stored document binary. Returns null if not found.
     */
    public function getContents(string $path): ?string
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

    public function snapshotPath(string $format, string $category, int $year, string $publicId, string $filename): string
    {
        return $this->path(implode('/', array_filter([
            $format,
            trim($category, '/'),
            (string) $year,
            trim($publicId, '/'),
            ltrim($filename, '/'),
        ], static fn (string $segment): bool => $segment !== '')));
    }
}
