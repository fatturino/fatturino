<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class CompanyLogoStorage
{
    private const DISK = 'logos';

    private const LEGACY_DISK = 'public';

    private const DIRECTORY = 'logos';

    /**
     * Persist an uploaded company logo and return the relative path.
     */
    public function store(UploadedFile $file): string
    {
        $disk = Storage::disk(self::DISK);
        $path = $file->store(self::DIRECTORY, self::DISK);

        if ($path === false || ! $disk->exists($path)) {
            throw new RuntimeException('Unable to persist company logo.');
        }

        return $path;
    }

    /**
     * Delete a logo from the durable disk and any legacy public copy.
     */
    public function delete(?string $path): void
    {
        if ($path === null || $path === '') {
            return;
        }

        Storage::disk(self::DISK)->delete($path);
        Storage::disk(self::LEGACY_DISK)->delete($path);
    }

    public function exists(?string $path): bool
    {
        if ($path === null || $path === '') {
            return false;
        }

        return $this->resolveDisk($path) !== null;
    }

    public function get(?string $path): ?string
    {
        $disk = $this->resolveDisk($path);

        return $disk === null ? null : Storage::disk($disk)->get($path);
    }

    public function mimeType(?string $path): ?string
    {
        $disk = $this->resolveDisk($path);

        if ($disk === null) {
            return null;
        }

        return Storage::disk($disk)->mimeType($path) ?: null;
    }

    /**
     * Resolve which disk holds the logo. Prefer durable logos, fall back to legacy public.
     */
    private function resolveDisk(?string $path): ?string
    {
        if ($path === null || $path === '') {
            return null;
        }

        if (Storage::disk(self::DISK)->exists($path)) {
            return self::DISK;
        }

        if (Storage::disk(self::LEGACY_DISK)->exists($path)) {
            return self::LEGACY_DISK;
        }

        return null;
    }
}
