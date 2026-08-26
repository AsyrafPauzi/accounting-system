<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

class ChangelogController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Settings/Changelog', [
            'meta'     => config('changelog.meta', []),
            'releases' => config('changelog.releases', []),
        ]);
    }
}
