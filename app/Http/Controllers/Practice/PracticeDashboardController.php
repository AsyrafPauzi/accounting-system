<?php

namespace App\Http\Controllers\Practice;

use App\Http\Controllers\Controller;
use App\Services\Practice\PracticeMetricsService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PracticeDashboardController extends Controller
{
    public function __construct(private readonly PracticeMetricsService $metrics)
    {
    }

    public function index(Request $request): Response
    {
        $user = $request->user();
        abort_unless($user && $user->isFirmUser(), 403);

        $firm = $user->firm;
        abort_unless($firm, 403);

        $clientRows = $this->metrics->clientRows($firm);
        $aggregates = $this->metrics->aggregates($clientRows);

        return Inertia::render('Practice/Dashboard', [
            'firm' => [
                'id'           => $firm->id,
                'name'         => $firm->name,
                'plan'         => $firm->subscription?->plan?->name,
                'plan_slug'    => $firm->subscription?->plan?->slug,
                'status'       => $firm->status,
                'client_cap'   => $firm->clientCap(),         // null = unlimited
                'client_count' => $firm->currentClientCount(),
                'remaining'    => $firm->clientsRemaining(),  // null = unlimited
                'can_add'      => $firm->canAddClient(),
            ],
            'aggregates' => $aggregates,
            'clients'    => $clientRows,
            // Two list-shaped projections used by the side panels —
            // computed here (not in the React component) so the
            // sorting / filtering logic is unit-testable in PHP.
            'attention'  => $this->metrics->clientsNeedingAttention($clientRows, 5),
            'deadlines'  => $this->metrics->upcomingDeadlines($clientRows, 90),
        ]);
    }
}
