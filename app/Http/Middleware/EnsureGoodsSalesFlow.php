<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureGoodsSalesFlow
{
    public function handle(Request $request, Closure $next): Response
    {
        $tenant = function_exists('tenant') ? tenant() : null;
        if ($tenant && $tenant->show_goods_flow === false) {
            abort(404);
        }

        return $next($request);
    }
}
