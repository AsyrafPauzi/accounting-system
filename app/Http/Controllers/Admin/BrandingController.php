<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BrandSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class BrandingController extends Controller
{
    /**
     * Platform-wide branding (product name, logo, accent palette).
     *
     * In self-hosted mode this lets the operator white-label their instance.
     * In SaaS mode it lets the platform owner (super-admin) restyle the
     * BukuCloud UI for every tenant. Access is already restricted to the
     * super-admin role at the route level, so no extra gate is needed here.
     */

    public function edit(): Response
    {
        $brand = BrandSettings::current();

        return Inertia::render('Admin/Branding/Edit', [
            'brand' => [
                'product_name' => $brand->product_name,
                'product_tagline' => $brand->product_tagline,
                'logo_path' => $brand->logo_path,
                'logo_url' => $brand->logo_path ? Storage::url($brand->logo_path) : null,
                'favicon_path' => $brand->favicon_path,
                'favicon_url' => $brand->favicon_path ? Storage::url($brand->favicon_path) : null,
                'color_terracotta' => $brand->color_terracotta,
                'color_forest' => $brand->color_forest,
                'color_mustard' => $brand->color_mustard,
            ],
            'defaults' => [
                'product_name' => 'BukuCloud',
                'color_terracotta' => '#C0492E',
                'color_forest' => '#0F4C3A',
                'color_mustard' => '#D4A537',
            ],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $hex = ['nullable', 'string', 'regex:/^#[A-Fa-f0-9]{6}$/'];

        $validated = $request->validate([
            'product_name' => ['nullable', 'string', 'max:80'],
            'product_tagline' => ['nullable', 'string', 'max:160'],
            'color_terracotta' => $hex,
            'color_forest' => $hex,
            'color_mustard' => $hex,
            'logo' => ['nullable', 'image', 'max:2048'],
            'favicon' => ['nullable', 'image', 'max:512'],
            'reset' => ['nullable', 'boolean'],
        ]);

        $brand = BrandSettings::current();

        if (! empty($validated['reset'])) {
            $brand->forceFill([
                'product_name' => null,
                'product_tagline' => null,
                'logo_path' => null,
                'favicon_path' => null,
                'color_terracotta' => null,
                'color_forest' => null,
                'color_mustard' => null,
            ])->save();

            return back()->with('success', 'Branding reset to BukuCloud defaults.');
        }

        $brand->product_name = $validated['product_name'] ?? null;
        $brand->product_tagline = $validated['product_tagline'] ?? null;
        $brand->color_terracotta = $validated['color_terracotta'] ?? null;
        $brand->color_forest = $validated['color_forest'] ?? null;
        $brand->color_mustard = $validated['color_mustard'] ?? null;

        if ($request->hasFile('logo')) {
            $brand->logo_path = $request->file('logo')->store('branding', 'public');
        }
        if ($request->hasFile('favicon')) {
            $brand->favicon_path = $request->file('favicon')->store('branding', 'public');
        }

        $brand->save();

        return back()->with('success', 'Branding updated.');
    }
}
