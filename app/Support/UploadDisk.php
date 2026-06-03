<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;
use League\Flysystem\Local\LocalFilesystemAdapter;

/**
 * Bill receipt uploads use the "public" disk (S3 in production). Admin OCR tests use the "local" disk.
 * On ECS, point FILESYSTEM_PUBLIC_DRIVER=s3 or mount EFS on storage/ (local driver).
 */
class UploadDisk
{
    public static function name(): string
    {
        return 'public';
    }

    public static function disk(): \Illuminate\Contracts\Filesystem\Filesystem
    {
        return Storage::disk(self::name());
    }

    public static function usesLocalDriver(): bool
    {
        return self::disk()->getAdapter() instanceof LocalFilesystemAdapter;
    }

    /**
     * Absolute path for local disk; copies cloud objects to a temp file for OCR binaries.
     */
    public static function absolutePathOrTemp(string $relativePath): ?string
    {
        $relativePath = trim($relativePath);
        if ($relativePath === '') {
            return null;
        }

        $disk = self::disk();

        try {
            if (! $disk->exists($relativePath)) {
                return null;
            }
        } catch (\Throwable) {
            return null;
        }

        if (self::usesLocalDriver()) {
            return $disk->path($relativePath);
        }

        $extension = pathinfo($relativePath, PATHINFO_EXTENSION) ?: 'bin';
        $tmp = tempnam(sys_get_temp_dir(), 'upload-').'.'.$extension;
        file_put_contents($tmp, $disk->get($relativePath));

        return $tmp;
    }
}
