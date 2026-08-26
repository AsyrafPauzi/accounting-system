<?php

namespace App\Http\Controllers;

use App\Models\FixedAsset;
use App\Services\FixedAssetService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class FixedAssetController extends Controller
{
    public function __construct(private FixedAssetService $assets) {}

    public function index(Request $request): Response
    {
        $this->authorize('journal.view');

        $assets = FixedAsset::query()
            ->orderByDesc('purchase_date')
            ->orderBy('name')
            ->get()
            ->map(fn (FixedAsset $asset) => [
                ...$asset->toArray(),
                'net_book_value' => $asset->netBookValue(),
                'monthly_depreciation' => $asset->monthlyDepreciation(),
            ]);

        return Inertia::render('FixedAssets/Index', [
            'assets' => $assets,
        ]);
    }

    public function create(): Response
    {
        $this->authorize('journal.create');

        return Inertia::render('FixedAssets/Form', [
            'asset' => null,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('journal.create');

        $validated = $request->validate([
            'name'               => 'required|string|max:150',
            'description'        => 'nullable|string|max:2000',
            'purchase_date'      => 'required|date',
            'cost'               => 'required|numeric|min:0.01',
            'salvage_value'      => 'nullable|numeric|min:0',
            'useful_life_months' => 'required|integer|min:1|max:600',
        ]);

        $this->assets->register($validated);

        return redirect()->route('fixed-assets.index')->with('success', 'Fixed asset registered.');
    }

    public function edit(int $id): Response
    {
        $this->authorize('journal.edit');

        return Inertia::render('FixedAssets/Form', [
            'asset' => FixedAsset::findOrFail($id),
        ]);
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $this->authorize('journal.edit');

        $asset = FixedAsset::findOrFail($id);
        $validated = $request->validate([
            'name'               => 'required|string|max:150',
            'description'        => 'nullable|string|max:2000',
            'purchase_date'      => 'required|date',
            'cost'               => 'required|numeric|min:0.01',
            'salvage_value'      => 'nullable|numeric|min:0',
            'useful_life_months' => 'required|integer|min:1|max:600',
        ]);

        try {
            $this->assets->update($asset, $validated);
        } catch (\LogicException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->route('fixed-assets.index')->with('success', 'Fixed asset updated.');
    }

    public function depreciate(Request $request, int $id): RedirectResponse
    {
        $this->authorize('journal.post');

        $asset = FixedAsset::findOrFail($id);
        $validated = $request->validate(['month' => 'required|date']);

        try {
            $monthEnd = \Carbon\Carbon::parse($validated['month'])->endOfMonth()->toDateString();
            $this->assets->depreciateMonth($asset, $monthEnd);
        } catch (\LogicException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->back()->with('success', 'Depreciation posted.');
    }

    public function dispose(Request $request, int $id): RedirectResponse
    {
        $this->authorize('journal.post');

        $asset = FixedAsset::findOrFail($id);
        $validated = $request->validate([
            'disposal_date'      => 'required|date',
            'disposal_proceeds'  => 'nullable|numeric|min:0',
            'bank_account_code'  => 'nullable|string|max:20',
        ]);

        try {
            $this->assets->dispose(
                $asset,
                (float) ($validated['disposal_proceeds'] ?? 0),
                $validated['disposal_date'],
                $validated['bank_account_code'] ?? '1200',
            );
        } catch (\LogicException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->route('fixed-assets.index')->with('success', 'Asset disposed and journal posted.');
    }
}
