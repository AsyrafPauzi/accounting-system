<?php

namespace App\Http\Controllers\Practice;

use App\Http\Controllers\Controller;
use App\Services\Practice\PracticeMetricsService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PracticeArController extends Controller
{
    public function __construct(private readonly PracticeMetricsService $metrics) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        abort_unless($user && $user->isFirmUser(), 403);

        $firm = $user->firm;
        abort_unless($firm, 403);

        $clientRows = $this->metrics->clientRows($firm);

        return Inertia::render('Practice/ArAging', [
            'firm' => [
                'id'   => $firm->id,
                'name' => $firm->name,
            ],
            'aggregates' => $this->metrics->aggregates($clientRows),
            'clients'    => $clientRows,
        ]);
    }
}
