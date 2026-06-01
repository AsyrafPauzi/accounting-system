<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\OcrSettingsRequest;
use App\Models\OcrSettings;
use App\Services\Ocr\OcrProviderResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class OcrSettingsController extends Controller
{
    public function __construct(
        private OcrProviderResolver $resolver,
    ) {}

    public function edit(): Response
    {
        $settings = OcrSettings::current();

        return Inertia::render('Admin/OcrSettings/Edit', [
            'settings' => [
                'provider' => $settings->provider,
                'gemini_model' => $settings->gemini_model,
                'gemini_api_key_masked' => $settings->maskedApiKey(),
                'has_gemini_api_key' => (bool) $settings->getDecryptedApiKey(),
                'tesseract_languages' => $settings->tesseract_languages,
                'max_image_mb' => $settings->max_image_mb,
            ],
            'providerOptions' => [
                [
                    'id' => OcrSettings::PROVIDER_DISABLED,
                    'name' => 'Disabled',
                    'tag' => 'No extraction',
                    'description' => 'Receipts are saved as-is. Users fill fields manually. Pick this if you do not want OCR running on this installation.',
                ],
                [
                    'id' => OcrSettings::PROVIDER_TESSERACT,
                    'name' => 'Tesseract',
                    'tag' => 'Free · Self-hosted',
                    'description' => 'Local OCR with the Tesseract engine. Free, private, ~75–85% accuracy on receipts. Requires the tesseract binary and language packs to be installed on the server.',
                ],
                [
                    'id' => OcrSettings::PROVIDER_GEMINI,
                    'name' => 'Gemini 1.5 Flash',
                    'tag' => 'Cloud · Pay-per-use',
                    'description' => 'Google\'s AI model. Highest accuracy, multilingual (English + Bahasa Malaysia native), ~$0.0003 per receipt. Requires a Gemini API key.',
                ],
            ],
            'modelOptions' => [
                ['id' => 'gemini-1.5-flash', 'name' => 'Gemini 1.5 Flash (recommended — fastest, cheapest)'],
                ['id' => 'gemini-1.5-flash-8b', 'name' => 'Gemini 1.5 Flash 8B (cheaper, slightly lower quality)'],
                ['id' => 'gemini-1.5-pro', 'name' => 'Gemini 1.5 Pro (most accurate, ~10× cost)'],
                ['id' => 'gemini-2.0-flash', 'name' => 'Gemini 2.0 Flash (latest)'],
            ],
            'languageOptions' => [
                ['id' => 'eng', 'name' => 'English'],
                ['id' => 'msa', 'name' => 'Bahasa Malaysia'],
                ['id' => 'ind', 'name' => 'Bahasa Indonesia'],
                ['id' => 'chi_sim', 'name' => 'Chinese (Simplified)'],
                ['id' => 'chi_tra', 'name' => 'Chinese (Traditional)'],
                ['id' => 'tha', 'name' => 'Thai'],
            ],
        ]);
    }

    public function update(OcrSettingsRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $settings = OcrSettings::current();

        $settings->provider = $validated['provider'];
        $settings->gemini_model = $validated['gemini_model'] ?: 'gemini-1.5-flash';
        $settings->tesseract_languages = $validated['tesseract_languages'] ?: 'eng+msa';
        $settings->max_image_mb = (int) ($validated['max_image_mb'] ?? 10);

        if (! empty($validated['clear_api_key'])) {
            $settings->setApiKey(null);
        } elseif (! empty($validated['gemini_api_key'])) {
            $settings->setApiKey($validated['gemini_api_key']);
        }
        // If neither flag is set, the existing key is preserved.

        $settings->save();

        return back()->with('success', 'OCR settings updated.');
    }

    /**
     * Run an admin-supplied receipt image through the active provider and return
     * the raw extracted output. Does NOT write to any bill.
     *
     * If a `receipt` file is uploaded, it's stored to a temporary location, OCR'd,
     * then deleted. If no file is uploaded but a static sample exists at
     * `public/samples/receipt-sample.jpg`, that's used as a fallback.
     */
    public function test(Request $request): JsonResponse
    {
        $request->validate([
            'receipt' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:10240'],
        ]);

        $imagePath = null;
        $cleanupPath = null;

        if ($request->hasFile('receipt')) {
            // Store on the public disk under a tmp prefix so the existing
            // resolveAbsolutePath() logic in providers can find it.
            $imagePath = $request->file('receipt')->store('ocr-tests', 'public');
            $cleanupPath = $imagePath;
        } elseif (file_exists(public_path('samples/receipt-sample.jpg'))) {
            $imagePath = 'samples/receipt-sample.jpg';
        } else {
            return response()->json([
                'ok' => false,
                'error' => 'Upload a receipt image or PDF to test, or drop a sample at public/samples/receipt-sample.jpg.',
            ], 422);
        }

        try {
            $provider = $this->resolver->resolve();
            $result = $provider->extract($imagePath);

            return response()->json([
                'ok' => $result->status === 'success',
                'provider' => $result->provider,
                'result' => $result->toArray(),
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'ok' => false,
                'error' => $e->getMessage(),
            ], 500);
        } finally {
            if ($cleanupPath) {
                Storage::disk('public')->delete($cleanupPath);
            }
        }
    }
}
