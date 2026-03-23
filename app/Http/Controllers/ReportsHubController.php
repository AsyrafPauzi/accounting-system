<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;

class ReportsHubController extends Controller
{
    /**
     * Reports hub: single entry point for all financial reports.
     */
    public function index(): Response
    {
        return Inertia::render('Reports/Hub');
    }
}
