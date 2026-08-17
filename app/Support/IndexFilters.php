<?php

namespace App\Support;

use Illuminate\Http\Request;

class IndexFilters
{
    /**
     * @return array{search: string, status: string, per_page: int}
     */
    public static function from(Request $request, int $defaultPerPage = 25): array
    {
        $search = trim((string) $request->input('search', ''));
        $status = (string) $request->input('status', '');
        if ($status === 'all') {
            $status = '';
        }

        $perPage = (int) $request->input('per_page', $defaultPerPage);
        if (! in_array($perPage, [10, 25, 50, 100], true)) {
            $perPage = $defaultPerPage;
        }

        return [
            'search'   => $search,
            'status'   => $status,
            'per_page' => $perPage,
        ];
    }
}
