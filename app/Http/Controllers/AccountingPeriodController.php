<?php

namespace App\Http\Controllers;

use App\Models\AccountingPeriod;
use App\Support\AccountingPeriodResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AccountingPeriodController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('periods.view');

        AccountingPeriodResolver::ensurePeriodsExist();

        $periods = AccountingPeriod::query()
            ->orderByDesc('start_date')
            ->limit(24)
            ->get()
            ->map(fn (AccountingPeriod $p) => [
                'id'         => $p->id,
                'label'      => $p->label,
                'start_date' => $p->start_date?->toDateString(),
                'end_date'   => $p->end_date?->toDateString(),
                'status'     => $p->status,
                'closed_at'  => $p->closed_at?->toDateTimeString(),
            ])
            ->values()
            ->all();

        return Inertia::render('Settings/AccountingPeriods', [
            'periods' => $periods,
            'canLock' => $request->user()?->can('periods.lock') ?? false,
            'canReopen' => $request->user()?->can('periods.reopen') ?? false,
        ]);
    }

    public function close(Request $request, AccountingPeriod $period): RedirectResponse
    {
        $this->authorize('periods.lock');

        if ($period->isClosed()) {
            return redirect()->back()->with('info', 'Period is already closed.');
        }

        $period->update([
            'status'    => 'closed',
            'closed_at' => now(),
            'closed_by' => $request->user()?->id,
        ]);

        return redirect()->back()->with('success', 'Accounting period '.$period->label.' closed.');
    }

    public function reopen(Request $request, AccountingPeriod $period): RedirectResponse
    {
        $this->authorize('periods.reopen');

        if (! $period->isClosed()) {
            return redirect()->back()->with('info', 'Period is already open.');
        }

        $period->update([
            'status'    => 'open',
            'closed_at' => null,
            'closed_by' => null,
        ]);

        return redirect()->back()->with('success', 'Accounting period '.$period->label.' reopened.');
    }
}
