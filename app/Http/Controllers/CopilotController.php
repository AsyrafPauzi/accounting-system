<?php

namespace App\Http\Controllers;

use App\Models\CopilotPendingAction;
use App\Services\Copilot\CopilotService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;
use Throwable;

class CopilotController extends Controller
{
    public function __construct(
        private CopilotService $copilot,
        private \App\Services\Copilot\CopilotCreditService $credits,
    ) {}

    public function show(Request $request): JsonResponse
    {
        $thread = $this->copilot->threadFor($request->user(), $request->integer('thread_id') ?: null);

        return response()->json($this->copilot->history($thread));
    }

    public function chat(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'message' => ['nullable', 'string', 'max:8000'],
            'thread_id' => ['nullable', 'integer'],
            'image' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:10240'],
        ]);

        $message = trim((string) ($validated['message'] ?? ''));
        if ($message === '' && ! $request->hasFile('image')) {
            return response()->json(['error' => 'Type a message or attach a receipt.'], 422);
        }

        if ($this->credits->meteringEnabled() && ! $request->session()->get('impersonator_id')) {
            $snap = $this->credits->snapshot();
            if (($snap['remaining'] ?? 0) < 1) {
                return response()->json([
                    'error' => 'No copilot credits remaining. Buy more from Plan & Usage.',
                    'credits' => $snap,
                    'code' => 'copilot_credits_exhausted',
                ], 402);
            }
        }

        $thread = $this->copilot->threadFor($request->user(), $validated['thread_id'] ?? null);

        try {
            $result = $this->copilot->chat(
                $request->user(),
                $thread,
                $message !== '' ? $message : 'Sila baca resit ini.',
                $request->file('image'),
            );
        } catch (RuntimeException $e) {
            $status = str_contains($e->getMessage(), 'credits') ? 402 : 422;

            return response()->json(['error' => $e->getMessage(), 'credits' => $this->credits->snapshot()], $status);
        } catch (Throwable $e) {
            report($e);

            return response()->json(['error' => 'Copilot could not complete that request.'], 500);
        }

        return response()->json($result);
    }

    public function confirm(Request $request, int $id): JsonResponse
    {
        $pending = CopilotPendingAction::query()->find($id);

        try {
            $result = $this->copilot->confirm($request->user(), $pending);
        } catch (RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return response()->json(['error' => $e->getMessage()], 403);
        } catch (Throwable $e) {
            report($e);

            return response()->json(['error' => $e->getMessage()], 422);
        }

        return response()->json($result);
    }

    public function cancel(Request $request, int $id): JsonResponse
    {
        $pending = CopilotPendingAction::query()
            ->where('created_by', $request->user()->id)
            ->where('status', CopilotPendingAction::STATUS_PENDING)
            ->find($id);

        if (! $pending) {
            return response()->json(['error' => 'No pending action to cancel.'], 422);
        }

        $pending->update(['status' => CopilotPendingAction::STATUS_CANCELLED]);

        $thread = $this->copilot->threadFor($request->user(), $pending->copilot_thread_id);

        return response()->json($this->copilot->history($thread) + ['ok' => true, 'id' => $pending->id]);
    }

    public function clear(Request $request): JsonResponse
    {
        $thread = $this->copilot->threadFor($request->user(), $request->integer('thread_id') ?: null);

        return response()->json($this->copilot->clear($thread));
    }
}
