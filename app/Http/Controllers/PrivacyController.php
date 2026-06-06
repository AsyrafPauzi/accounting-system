<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;

/**
 * Renders the public privacy-policy page. Public access is intentional —
 * users need to be able to read it before they sign up, and search
 * engines should be able to index it as a sign of compliance maturity.
 */
class PrivacyController extends Controller
{
    public function show(): Response
    {
        return Inertia::render('Public/Privacy', [
            'version'       => config('privacy.current_version'),
            'dpoEmail'      => config('privacy.dpo_email'),
            'controller'    => config('privacy.controller_name'),
        ]);
    }
}
