<?php

namespace App\Http\Controllers;

use App\Models\DocumentNumberSetting;
use App\Support\DocumentNumberDefaults;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DocumentNumberSettingsController extends Controller
{
    public function edit(Request $request): Response
    {
        $this->authorize('settings.view');

        DocumentNumberDefaults::seedMissing();

        $settings = DocumentNumberSetting::query()
            ->orderBy('doc_type')
            ->get()
            ->map(fn (DocumentNumberSetting $row) => [
                'doc_type'    => $row->doc_type,
                'label'       => str_replace('_', ' ', ucfirst($row->doc_type)),
                'prefix'      => $row->prefix,
                'next_number' => $row->next_number,
                'pad_width'   => $row->pad_width,
                'reset_on_fy' => $row->reset_on_fy,
            ])
            ->values()
            ->all();

        return Inertia::render('Settings/DocumentNumbers', [
            'settings' => $settings,
            'canEdit'  => $request->user()?->canAdminCurrentTenant() ?? false,
            'financial_year_start_month' => (int) (tenant()?->financial_year_start_month ?? 1),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $this->authorize('settings.edit');

        $validated = $request->validate([
            'settings'                    => 'required|array|min:1',
            'settings.*.doc_type'         => 'required|string|max:40',
            'settings.*.prefix'           => 'required|string|max:20',
            'settings.*.next_number'      => 'required|integer|min:1',
            'settings.*.pad_width'        => 'required|integer|min:2|max:10',
            'settings.*.reset_on_fy'      => 'boolean',
        ]);

        foreach ($validated['settings'] as $row) {
            DocumentNumberSetting::query()
                ->where('doc_type', $row['doc_type'])
                ->update([
                    'prefix'      => strtoupper(trim($row['prefix'])),
                    'next_number' => (int) $row['next_number'],
                    'pad_width'   => (int) $row['pad_width'],
                    'reset_on_fy' => (bool) ($row['reset_on_fy'] ?? false),
                ]);
        }

        return redirect()->back()->with('success', 'Document numbering settings saved.');
    }
}
