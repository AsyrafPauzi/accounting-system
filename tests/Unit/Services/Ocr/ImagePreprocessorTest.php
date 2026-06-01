<?php

namespace Tests\Unit\Services\Ocr;

use App\Services\Ocr\ImagePreprocessor;
use Tests\TestCase;

/**
 * Smoke tests for ImagePreprocessor. The unit tests do NOT exercise ImageMagick
 * itself (third-party binary, not our concern) — they exercise the wrapper:
 *
 *   1. When the binary is missing, return the input path unchanged
 *      (so OCR keeps working in degraded environments).
 *   2. When the binary IS available, produce a valid PNG at a NEW path.
 *
 * Extends Tests\TestCase so Laravel's facades (Log, storage_path) bootstrap.
 */
class ImagePreprocessorTest extends TestCase
{
    public function test_returns_input_unchanged_when_imagemagick_missing(): void
    {
        $preprocessor = new ImagePreprocessor(magickBinary: '/non/existent/magick');
        $input = sys_get_temp_dir() . '/preprocessor-input-' . uniqid() . '.png';
        file_put_contents($input, "fake png contents");

        $output = $preprocessor->process($input);

        $this->assertSame($input, $output, 'Should return input path verbatim when magick is unavailable');
        @unlink($input);
    }

    public function test_returns_input_unchanged_when_input_unreadable(): void
    {
        $preprocessor = new ImagePreprocessor();
        $output = $preprocessor->process('/non/existent/file.png');

        $this->assertSame('/non/existent/file.png', $output);
    }

    public function test_processes_image_with_imagemagick_when_available(): void
    {
        $magick = trim((string) shell_exec('command -v magick'));
        if ($magick === '') {
            $this->markTestSkipped('ImageMagick binary not installed locally — install with `brew install imagemagick`.');
        }

        // Create a synthetic JPEG via ImageMagick. We don't render text (that
        // requires a font that may be absent on macOS) — a plain canvas is
        // enough to exercise the preprocess pipeline.
        $input = sys_get_temp_dir() . '/preprocessor-input-' . uniqid() . '.jpg';
        $createCmd = escapeshellarg($magick) . ' -size 800x400 xc:white ' . escapeshellarg($input);
        exec($createCmd . ' 2>&1', $createOutput, $createExit);

        $this->assertSame(0, $createExit, 'Failed to create test JPEG: ' . implode("\n", $createOutput));
        $this->assertFileExists($input);

        $preprocessor = new ImagePreprocessor(magickBinary: $magick);
        $output = $preprocessor->process($input);

        $this->assertNotSame($input, $output, 'Should produce a NEW path when magick succeeds');
        $this->assertFileExists($output);
        $this->assertStringEndsWith('.png', $output, 'Output should be PNG');
        $this->assertGreaterThan(0, filesize($output));

        @unlink($input);
        @unlink($output);
    }
}
