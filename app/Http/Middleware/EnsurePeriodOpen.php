<?php

namespace App\Http\Middleware;

use App\Support\AccountingPeriodResolver;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePeriodOpen
{
    /**
     * @param  list<string>  $dateFields  Request keys to check, in order.
     */
    public function handle(Request $request, Closure $next, string ...$dateFields): Response
    {
        foreach ($dateFields as $field) {
            $value = $request->input($field);
            if ($value) {
                try {
                    AccountingPeriodResolver::assertOpenForDate((string) $value);
                } catch (\LogicException $e) {
                    if ($request->expectsJson()) {
                        return response()->json(['message' => $e->getMessage()], 422);
                    }

                    return redirect()->back()->with('error', $e->getMessage());
                }
            }
        }

        return $next($request);
    }
}
