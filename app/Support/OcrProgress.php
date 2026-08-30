<?php

namespace App\Support;

final class OcrProgress
{
    /**
     * @return array{phase: string, progress: int, label: string}
     */
    public static function forUploadPercent(int $uploadPercent): array
    {
        $uploadPercent = max(0, min(100, $uploadPercent));
        $progress = (int) round($uploadPercent * 0.25);

        return [
            'phase' => 'upload',
            'progress' => $progress,
            'label' => 'Uploading invoice…',
        ];
    }

    /**
     * @return array{phase: string, progress: int, label: string}
     */
    public static function forPending(int $elapsedMs): array
    {
        if ($elapsedMs < 2_000) {
            $t = max(0, min(1, $elapsedMs / 2_000));
            $progress = (int) round(25 + ($t * 10));

            return [
                'phase' => 'queued',
                'progress' => $progress,
                'label' => 'Waiting for scan…',
            ];
        }

        $t = max(0, min(1, ($elapsedMs - 2_000) / 60_000));
        $progress = (int) round(35 + ($t * 55));

        return [
            'phase' => 'processing',
            'progress' => min(90, $progress),
            'label' => 'Scanning invoice…',
        ];
    }

    /**
     * @return array{phase: string, progress: int, label: string}
     */
    public static function completed(): array
    {
        return [
            'phase' => 'done',
            'progress' => 100,
            'label' => 'Scan complete',
        ];
    }

    /**
     * @return array{phase: string, progress: int, label: string}
     */
    public static function failed(): array
    {
        return [
            'phase' => 'failed',
            'progress' => 100,
            'label' => 'Scan failed — enter details manually',
        ];
    }
}
