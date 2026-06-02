<?php

namespace App\Services\Ocr\Providers;

use App\Models\OcrSettings;
use App\Services\Ocr\ImagePreprocessor;
use App\Services\Ocr\OcrProviderInterface;
use App\Services\Ocr\OcrResult;
use App\Services\Ocr\OcrTextNormalizer;
use App\Services\Ocr\OcrValidator;
use App\Services\Ocr\PdfPreprocessor;
use App\Services\Ocr\ReceiptParser;
use App\Services\Ocr\TsvParser;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use thiagoalessio\TesseractOCR\TesseractOCR;
use thiagoalessio\TesseractOCR\TesseractOcrException;

/**
 * Local OCR using the Tesseract CLI binary, post-processed by ReceiptParser.
 *
 * Requires:
 *   - The `tesseract` binary on PATH (Linux: tesseract-ocr package)
 *   - Language packs matching the configured codes (e.g. tesseract-ocr-eng, tesseract-ocr-msa)
 *
 * The provider takes a storage-relative path on the 'public' disk
 * (matching how BillController writes receipts today). When/if the S3 storage
 * plan ships, we'll switch to reading bytes from the 's3' disk and writing
 * them to a temp file before invoking Tesseract.
 */
class TesseractProvider implements OcrProviderInterface
{
    public function __construct(
        private ReceiptParser $parser,
        private PdfPreprocessor $pdfPreprocessor,
        private ImagePreprocessor $imagePreprocessor,
        private OcrTextNormalizer $textNormalizer,
        private OcrValidator $validator,
        private TsvParser $tsvParser,
    ) {}

    public function name(): string
    {
        return 'tesseract';
    }

    public function extract(string $imagePath): OcrResult
    {
        $absolutePath = $this->resolveAbsolutePath($imagePath);

        if (! $absolutePath) {
            return OcrResult::failed(
                provider: $this->name(),
                error: "Receipt file not found at $imagePath. Cannot run Tesseract.",
            );
        }

        // PDFs take a different code path: try text extraction first
        // (faster + more accurate for digital PDFs), fall back to rendering
        // page 1 to PNG and OCRing it.
        if ($this->isPdf($absolutePath)) {
            return $this->extractFromPdf($absolutePath);
        }

        return $this->extractFromImage($absolutePath);
    }

    /** PSMs we try in priority order. PSM 4 favours single-column receipts;
     *  PSM 6 handles dense tabular invoices better. */
    private const PSM_CANDIDATES = [4, 6];

    /** If the first PSM scores at least this many points, we skip the second
     *  one to save ~1s of OCR time. Threshold tuned: vendor (3) + total (5) +
     *  3 line items (3) = 11. */
    private const PSM_EARLY_EXIT_SCORE = 11;

    /** Minimum parse score to accept the fast original-only pass and skip preprocess. */
    private const FAST_ORIGINAL_MIN_SCORE = 5;

    private function extractFromImage(string $absolutePath, ?callable $cleanup = null): OcrResult
    {
        $settings = OcrSettings::current();
        $languages = $settings->tesseract_languages ?: 'eng+msa';
        $lastError = null;

        // Try the raw upload first (PSM 6, text-only). On Fargate this is ~10–20s and
        // matches local Laragon where ImageMagick is absent. Skip slow preprocess when
        // the original already yields a usable total / vendor parse.
        $fastOriginal = $this->attemptOcrOnTarget($absolutePath, $languages, $absolutePath, fast: true);
        if ($fastOriginal['result'] !== null) {
            $fastScore = $this->scoreFields($fastOriginal['result']->toLegacyArray()['data'] ?? []);
            if ($fastScore >= self::FAST_ORIGINAL_MIN_SCORE) {
                if ($cleanup) {
                    $cleanup();
                }
                Log::debug('[OCR/Tesseract] Fast original pass sufficient', ['score' => $fastScore]);

                return $this->validator->validate($fastOriginal['result']);
            }
        }
        $lastError = $fastOriginal['lastError'];

        $bestResult = $fastOriginal['result'];
        $bestScore = $bestResult !== null
            ? $this->scoreFields($bestResult->toLegacyArray()['data'] ?? [])
            : -1;

        // Harder receipts: ImageMagick preprocess + full Tesseract (with TSV).
        $preprocessed = $this->imagePreprocessor->process($absolutePath);
        if ($preprocessed !== $absolutePath) {
            $attempt = $this->attemptOcrOnTarget($preprocessed, $languages, $absolutePath);
            @unlink($preprocessed);

            if ($attempt['result'] !== null) {
                $score = $this->scoreFields($attempt['result']->toLegacyArray()['data'] ?? []);
                if ($score > $bestScore) {
                    $bestScore = $score;
                    $bestResult = $attempt['result'];
                }
                if ($score >= self::PSM_EARLY_EXIT_SCORE) {
                    if ($cleanup) {
                        $cleanup();
                    }

                    return $this->validator->validate($attempt['result']);
                }
            }

            $lastError = $attempt['lastError'] ?? $lastError;
            Log::info('[OCR/Tesseract] Preprocessed pass insufficient, keeping best candidate', [
                'path' => $absolutePath,
                'best_score' => $bestScore,
            ]);
        }

        // Last resort: full OCR on the original (PSM 4+6 + TSV) if fast pass was weak.
        if ($bestScore < self::PSM_EARLY_EXIT_SCORE) {
            $fullOriginal = $this->attemptOcrOnTarget($absolutePath, $languages, $absolutePath);
            if ($fullOriginal['result'] !== null) {
                $score = $this->scoreFields($fullOriginal['result']->toLegacyArray()['data'] ?? []);
                if ($score > $bestScore) {
                    $bestScore = $score;
                    $bestResult = $fullOriginal['result'];
                }
            }
            $lastError = $fullOriginal['lastError'] ?? $lastError;
        }

        if ($cleanup) {
            $cleanup();
        }

        if ($bestResult === null) {
            return OcrResult::failed(
                provider: $this->name(),
                error: $lastError ?? 'Tesseract produced no readable text on any page-segmentation mode. The image may be too blurry, dark, or low-contrast.',
            );
        }

        return $this->validator->validate($bestResult);
    }

    /**
     * Try each PSM mode against one image path; return the best-scoring parse.
     *
     * @return array{result: ?OcrResult, lastError: ?string}
     */
    private function attemptOcrOnTarget(string $ocrTarget, string $languages, string $logPath, bool $fast = false): array
    {
        $bestResult = null;
        $bestScore = -1;
        $lastError = null;
        $psmCandidates = $fast ? [6] : self::PSM_CANDIDATES;

        foreach ($psmCandidates as $psm) {
            try {
                $ocrOutput = $this->runOcrAttempt($ocrTarget, $languages, $psm, includeTsv: ! $fast);
            } catch (TesseractOcrException $e) {
                $lastError = 'Tesseract OCR engine failed. Check that the tesseract binary and the requested language packs are installed on the server. Error: '.$e->getMessage();
                Log::error('[OCR/Tesseract] Engine error', [
                    'path' => $logPath, 'ocr_target' => $ocrTarget, 'psm' => $psm, 'error' => $e->getMessage(),
                ]);
                // Unreadable preprocessed PNG — skip remaining PSMs on this target.
                if (str_contains($e->getMessage(), 'did not produce any output')) {
                    break;
                }
                continue;
            } catch (\Throwable $e) {
                $lastError = 'Unexpected error while running Tesseract: '.$e->getMessage();
                Log::error('[OCR/Tesseract] Unexpected error', [
                    'path' => $logPath, 'ocr_target' => $ocrTarget, 'psm' => $psm, 'error' => $e->getMessage(),
                ]);
                continue;
            }

            if (trim($ocrOutput['text']) === '') {
                continue;
            }

            $candidate = $this->buildResultFromOcrOutput($ocrOutput);
            $score = $this->scoreFields($candidate->toLegacyArray()['data'] ?? []);

            Log::debug('[OCR/Tesseract] PSM attempt', ['psm' => $psm, 'score' => $score, 'ocr_target' => $ocrTarget]);

            if ($score > $bestScore) {
                $bestScore = $score;
                $bestResult = $candidate;
            }

            if ($score >= self::PSM_EARLY_EXIT_SCORE) {
                break;
            }
        }

        return ['result' => $bestResult, 'lastError' => $lastError];
    }

    /**
     * Run Tesseract twice on the same image — once for plain text, once for TSV
     * (with bounding boxes) — at the given PSM. Returns both outputs.
     *
     * @return array{text: string, tsv: string}
     */
    private function runOcrAttempt(string $ocrTarget, string $languages, int $psm, bool $includeTsv = true): array
    {
        $textRunner = (new TesseractOCR($ocrTarget))
            ->lang(...explode('+', $languages))
            ->psm($psm);
        $text = $textRunner->run();

        // TSV mode failure is non-fatal — text-only is still useful.
        $tsv = '';
        if ($includeTsv) {
            try {
                $tsvRunner = (new TesseractOCR($ocrTarget))
                    ->lang(...explode('+', $languages))
                    ->psm($psm)
                    ->configFile('tsv');
                $tsv = $tsvRunner->run();
            } catch (\Throwable $e) {
                Log::info('[OCR/Tesseract] TSV mode unavailable, continuing text-only', ['psm' => $psm, 'error' => $e->getMessage()]);
            }
        }

        return ['text' => $text, 'tsv' => $tsv];
    }

    /**
     * Build an OcrResult from raw text + TSV using the full parsing pipeline.
     * Does NOT run the validator (caller does that on the winning candidate).
     */
    private function buildResultFromOcrOutput(array $ocrOutput): OcrResult
    {
        // Layer 3: fix common OCR confusions inside money/date contexts.
        $normalizedText = $this->textNormalizer->normalize($ocrOutput['text']);

        $fields = $this->parser->parse($normalizedText);

        // Layer 2: prefer spatial item extraction over line-based regex when available.
        if ($ocrOutput['tsv'] !== '') {
            $tsvFields = $this->tsvParser->parse($ocrOutput['tsv']);
            if (! empty($tsvFields['items'])) {
                $fields['items'] = $tsvFields['items'];
            }
        }

        return OcrResult::success($this->name(), $fields);
    }

    /**
     * Score a parsed result by completeness. Higher = more useful extraction.
     * Used to compare PSM attempts against each other.
     */
    private function scoreFields(array $data): int
    {
        $score = 0;
        if (! empty($data['supplier_name'])) $score += 3;
        if (! empty($data['total_amount'])) $score += 5;
        if (! empty($data['bill_date'])) $score += 2;
        if (! empty($data['reference'])) $score += 2;
        if (! empty($data['subtotal'])) $score += 1;
        $score += min(count($data['items'] ?? []), 5);
        return $score;
    }

    private function extractFromPdf(string $absolutePath): OcrResult
    {
        $pre = $this->pdfPreprocessor->preprocess($absolutePath);

        // Direct text extraction worked → parse and return without OCR.
        if ($pre['mode'] === 'text' && ! empty($pre['text'])) {
            $fields = $this->parser->parse($pre['text']);
            $result = OcrResult::success($this->name(), $fields);
            return $this->validator->validate($result);
        }

        // PDF→PNG fallback failed → return preprocessing error.
        if ($pre['error']) {
            return OcrResult::failed(
                provider: $this->name(),
                error: 'Could not read PDF. '.$pre['error'],
            );
        }

        if (! $pre['image_path']) {
            return OcrResult::failed(
                provider: $this->name(),
                error: 'PDF preprocessing returned no usable output.',
            );
        }

        return $this->extractFromImage($pre['image_path'], $pre['cleanup']);
    }

    private function isPdf(string $absolutePath): bool
    {
        // Cheap MIME sniff via magic bytes; works for any extension/casing.
        $handle = fopen($absolutePath, 'rb');
        if (! $handle) return false;
        $header = fread($handle, 4);
        fclose($handle);
        return $header === '%PDF';
    }

    /**
     * Resolve a storage-relative path to an absolute filesystem path.
     * Falls back to checking sample assets in public/.
     */
    private function resolveAbsolutePath(string $imagePath): ?string
    {
        // 1. Storage 'public' disk (where BillController::uploadReceipt writes today)
        if (Storage::disk('public')->exists($imagePath)) {
            return Storage::disk('public')->path($imagePath);
        }

        // 2. Sample assets shipped under public/ (used by the test button)
        $publicSample = public_path($imagePath);
        if (file_exists($publicSample)) {
            return $publicSample;
        }

        // 3. Already an absolute path
        if (str_starts_with($imagePath, '/') && file_exists($imagePath)) {
            return $imagePath;
        }

        return null;
    }
}
