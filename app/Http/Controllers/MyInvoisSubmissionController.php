<?php

namespace App\Http\Controllers;

use App\Models\MyInvoisSubmission;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MyInvoisSubmissionController extends Controller
{
    public function index(Request $request): Response
    {
        $validated = $request->validate([
            'status'     => 'nullable|string|in:submitted,accepted,rejected,error',
            'date_from'  => 'nullable|date',
            'date_to'    => 'nullable|date|after_or_equal:date_from',
        ]);

        $query = MyInvoisSubmission::query()->orderByDesc('submitted_at');

        if (! empty($validated['status'])) {
            $query->where('status', $validated['status']);
        }
        if (! empty($validated['date_from'])) {
            $query->whereDate('submitted_at', '>=', $validated['date_from']);
        }
        if (! empty($validated['date_to'])) {
            $query->whereDate('submitted_at', '<=', $validated['date_to']);
        }

        $submissions = $query
            ->paginate(25)
            ->withQueryString()
            ->through(fn (MyInvoisSubmission $row) => [
                'id'            => $row->id,
                'document_type' => $row->document_type,
                'document_id'   => $row->document_id,
                'status'        => $row->status,
                'http_status'   => $row->http_status,
                'lhdn_uuid'     => $row->lhdn_uuid,
                'submitted_at'  => $row->submitted_at?->toIso8601String(),
            ]);

        return Inertia::render('MyInvois/Submissions', [
            'submissions' => $submissions,
            'filters'     => [
                'status'    => $validated['status'] ?? '',
                'date_from' => $validated['date_from'] ?? '',
                'date_to'   => $validated['date_to'] ?? '',
            ],
        ]);
    }

    public function show(int $id): Response
    {
        $submission = MyInvoisSubmission::query()->findOrFail($id);

        return Inertia::render('MyInvois/Submissions', [
            'submissions' => null,
            'selected'    => [
                'id'            => $submission->id,
                'document_type' => $submission->document_type,
                'document_id'   => $submission->document_id,
                'status'        => $submission->status,
                'http_status'   => $submission->http_status,
                'lhdn_uuid'     => $submission->lhdn_uuid,
                'submitted_at'  => $submission->submitted_at?->toIso8601String(),
                'request_json'  => $submission->request_json,
                'response_json' => $submission->response_json,
            ],
        ]);
    }
}
