<?php

namespace App\Services\Copilot;

use App\Models\CopilotMessage;
use App\Models\CopilotPendingAction;
use App\Models\CopilotThread;
use App\Models\User;
use App\Services\Ai\IlmuClient;
use App\Services\Ocr\Providers\IlmuProvider;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Session;
use RuntimeException;
use Throwable;

class CopilotService
{
    public const MAX_MODEL_MESSAGES = 8;

    public const MAX_STORED_MESSAGES = 16;

    public const SYSTEM_PROMPT = <<<'PROMPT'
You are Accountant copilot for BukuCloud, a Malaysian SME accounting system.
Reply in Bahasa Malaysia or English to match the user. Keep answers short — chat length, not a document.
Reads run immediately. Drafts and postings must go through tools — never claim you saved, posted, emailed, or submitted to MyInvois unless a tool result says so.
Never invent TIN or BRN. If identification is missing, say so.
Never invent account balances. Use tools.
When proposing a write, call the matching tool so the user can Confirm. Do not tell them the write already happened.
For draft_bill_from_receipt always include vendor_name from OCR (and tin/phone/email when present). Confirm will create the supplier if it does not exist yet and link it to the bill.
If the user wants a customer statement PDF/report/download, call download_customer_statement_pdf and give them the pdf_url link. Only use email_customer_statement when they explicitly ask to email or send it.
If user paid a business expense from personal money and wants to claim/reimburse, use draft_owner_expense_claim (do not invent other journal paths).
To add a teammate, use invite_team_member (only if seats available); never claim payment for extra seats succeeded.
For SO→DO→invoice / cancel SO / return DO use the matching sales tools.

Formatting (markdown):
- Use **bold** for invoice numbers, customer names, and amounts (e.g. **INV-1**, **RM12.95**).
- Lists of documents: one bullet per row, like `- **INV-1** · Tropika Sdn Bhd · **RM12.95** · 3 days overdue`
- Line items: a short heading **Items** then one bullet each: `- Service name · 2 × RM150`
- Ask at most one follow-up question. Do not dump JSON or long numbered questionnaires.
PROMPT;

    public function __construct(
        private CopilotTools $tools,
        private IlmuProvider $ocr,
        private CopilotCreditService $credits,
    ) {}

    public function threadFor(User $user, ?int $threadId = null): CopilotThread
    {
        if ($threadId) {
            return CopilotThread::query()
                ->where('user_id', $user->id)
                ->whereKey($threadId)
                ->firstOrFail();
        }

        $existing = CopilotThread::query()
            ->where('user_id', $user->id)
            ->latest('id')
            ->first();

        if ($existing) {
            return $existing;
        }

        return CopilotThread::query()->create(['user_id' => $user->id, 'title' => 'Accountant copilot']);
    }

    /**
     * @return array<string, mixed>
     */
    public function history(CopilotThread $thread): array
    {
        $messages = $thread->messages()->orderByDesc('id')->limit(self::MAX_STORED_MESSAGES)->get()->reverse()->values()->map(fn (CopilotMessage $m) => [
            'id' => $m->id,
            'role' => $m->role,
            'content' => $m->content,
            'tool_traces' => $m->tool_traces,
        ])->all();

        $pending = $thread->pendingActions()
            ->where('status', CopilotPendingAction::STATUS_PENDING)
            ->orderByDesc('id')
            ->get()
            ->map(fn (CopilotPendingAction $p) => $this->pendingPayload($p))
            ->all();

        return [
            'thread_id' => $thread->id,
            'title' => $thread->title,
            'messages' => $messages,
            'pending_actions' => $pending,
            'credits' => $this->credits->snapshot(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function chat(User $user, CopilotThread $thread, string $message, ?UploadedFile $image = null): array
    {
        $burned = false;
        $impersonating = (bool) Session::get('impersonator_id');
        if (! $impersonating && $this->credits->meteringEnabled()) {
            $this->credits->burnOne($user);
            $burned = true;
        }

        try {
            return $this->runChat($user, $thread, $message, $image);
        } catch (Throwable $e) {
            // Refund only if we never reached the provider (e.g. client misconfigured).
            if ($burned && $e instanceof RuntimeException && str_contains($e->getMessage(), 'ILMU')) {
                $this->credits->refundOne($user, 'ilmu_client_unavailable');
            }
            throw $e;
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function runChat(User $user, CopilotThread $thread, string $message, ?UploadedFile $image = null): array
    {
        $ocrNote = null;
        $receiptPath = null;
        if ($image) {
            // Same disk/prefix as bill uploads so Receipt Preview can serve the file.
            $receiptPath = $image->store('receipts', 'public');
            $ocr = $this->ocr->extract(is_string($receiptPath) ? $receiptPath : '');
            $ocrNote = json_encode($ocr->toArray(), JSON_UNESCAPED_UNICODE);
        }

        $storedUser = $message;
        if ($ocrNote) {
            $storedUser = trim($message.' [receipt attached]');
        }

        $thread->messages()->create([
            'role' => 'user',
            'content' => $storedUser,
        ]);

        if (! $thread->title || $thread->title === 'Accountant copilot') {
            $thread->update(['title' => mb_substr(trim($message) !== '' ? $message : 'Receipt', 0, 80)]);
        }

        $client = IlmuClient::fromSettings();
        $openaiMessages = $this->buildModelMessages($thread);
        if ($ocrNote) {
            $last = count($openaiMessages) - 1;
            if ($last >= 1 && ($openaiMessages[$last]['role'] ?? '') === 'user') {
                $openaiMessages[$last]['content'] .= "\n\nReceipt OCR JSON:\n".$ocrNote."\nreceipt_path=".($receiptPath ?? '');
            }
        }
        $pendingCreated = [];
        $traces = [];

        for ($i = 0; $i < 6; $i++) {
            $payload = $client->chat($openaiMessages, CopilotCatalog::openaiTools(), jsonMode: false);
            $toolCalls = IlmuClient::toolCalls($payload);
            $assistantMsg = data_get($payload, 'choices.0.message', []);

            if ($toolCalls === []) {
                $text = IlmuClient::messageText($payload) ?: 'Saya tidak dapat menjawab sekarang.';
                $thread->messages()->create([
                    'role' => 'assistant',
                    'content' => $text,
                    'tool_traces' => $traces ?: null,
                ]);
                $this->prune($thread);

                return $this->history($thread) + ['reply' => $text, 'new_pending' => $pendingCreated];
            }

            $openaiMessages[] = [
                'role' => 'assistant',
                'content' => $assistantMsg['content'] ?? null,
                'tool_calls' => $toolCalls,
            ];

            foreach ($toolCalls as $call) {
                $name = (string) data_get($call, 'function.name');
                $rawArgs = (string) data_get($call, 'function.arguments', '{}');
                $args = json_decode($rawArgs, true);
                if (! is_array($args)) {
                    $args = [];
                }
                if ($receiptPath && empty($args['receipt_path'])) {
                    $args['receipt_path'] = $receiptPath;
                }

                $risk = CopilotCatalog::risk($name);
                if ($risk === CopilotCatalog::RISK_READ) {
                    try {
                        $result = $this->tools->execute($name, $args, $user);
                        $traces[] = ['tool' => $name, 'risk' => $risk, 'ok' => true];
                    } catch (Throwable $e) {
                        $result = ['ok' => false, 'error' => $e->getMessage()];
                        $traces[] = ['tool' => $name, 'risk' => $risk, 'ok' => false];
                    }
                    $openaiMessages[] = [
                        'role' => 'tool',
                        'tool_call_id' => $call['id'] ?? $name,
                        'content' => json_encode($result, JSON_UNESCAPED_UNICODE),
                    ];
                    continue;
                }

                try {
                    $this->tools->assertAllowed($user, $name);
                    $pending = CopilotPendingAction::query()->create([
                        'copilot_thread_id' => $thread->id,
                        'created_by' => $user->id,
                        'tool_name' => $name,
                        'risk' => $risk,
                        'payload' => $args,
                        'summary' => $this->summarise($name, $args),
                        'status' => CopilotPendingAction::STATUS_PENDING,
                    ]);
                    $card = $this->pendingPayload($pending);
                    $pendingCreated[] = $card;
                    $result = [
                        'queued' => true,
                        'pending_id' => $pending->id,
                        'message' => 'Action queued. The user must click Confirm before this write runs. Do not claim it succeeded.',
                        'preview' => $args,
                    ];
                    $traces[] = ['tool' => $name, 'risk' => $risk, 'pending_id' => $pending->id];
                } catch (AuthorizationException $e) {
                    $result = ['ok' => false, 'error' => $e->getMessage()];
                    $traces[] = ['tool' => $name, 'risk' => $risk, 'ok' => false];
                }

                $openaiMessages[] = [
                    'role' => 'tool',
                    'tool_call_id' => $call['id'] ?? $name,
                    'content' => json_encode($result, JSON_UNESCAPED_UNICODE),
                ];
            }
        }

        $fallback = 'Sila Confirm tindakan yang menunggu, atau tanya semula.';
        $thread->messages()->create([
            'role' => 'assistant',
            'content' => $fallback,
            'tool_traces' => $traces ?: null,
        ]);
        $this->prune($thread);

        return $this->history($thread) + ['reply' => $fallback, 'new_pending' => $pendingCreated];
    }

    /**
     * @return array<string, mixed>
     */
    public function confirm(User $user, ?CopilotPendingAction $pending): array
    {
        $pending = self::requirePending($pending);
        if ((int) $pending->created_by !== (int) $user->id) {
            throw new AuthorizationException('This confirmation belongs to another user.');
        }

        try {
            $result = $this->tools->execute($pending->tool_name, $pending->payload ?? [], $user);
            $pending->update([
                'status' => CopilotPendingAction::STATUS_CONFIRMED,
                'result' => $result,
                'error' => null,
            ]);
            $pending->thread->messages()->create([
                'role' => 'assistant',
                'content' => $this->confirmChatLine($pending, $result),
            ]);
            $this->prune($pending->thread);

            return ['ok' => true, 'pending' => $this->pendingPayload($pending->fresh()), 'result' => $result];
        } catch (Throwable $e) {
            $pending->update([
                'status' => CopilotPendingAction::STATUS_FAILED,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    public function clear(CopilotThread $thread): array
    {
        $thread->pendingActions()
            ->where('status', CopilotPendingAction::STATUS_PENDING)
            ->update(['status' => CopilotPendingAction::STATUS_CANCELLED]);
        $thread->messages()->delete();
        $thread->update(['title' => 'Accountant copilot']);

        return $this->history($thread->fresh());
    }

    public static function requirePending(?CopilotPendingAction $pending): CopilotPendingAction
    {
        if (! $pending || $pending->status !== CopilotPendingAction::STATUS_PENDING) {
            throw new RuntimeException('Confirm refused: no pending action.');
        }

        return $pending;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function buildModelMessages(CopilotThread $thread): array
    {
        $messages = [
            ['role' => 'system', 'content' => self::SYSTEM_PROMPT],
        ];
        $rows = $thread->messages()
            ->whereIn('role', ['user', 'assistant'])
            ->orderByDesc('id')
            ->limit(self::MAX_MODEL_MESSAGES)
            ->get()
            ->reverse();
        foreach ($rows as $row) {
            $messages[] = [
                'role' => $row->role,
                'content' => (string) $row->content,
            ];
        }

        return $messages;
    }

    private function prune(CopilotThread $thread): void
    {
        $keepIds = $thread->messages()
            ->orderByDesc('id')
            ->limit(self::MAX_STORED_MESSAGES)
            ->pluck('id');
        if ($keepIds->isEmpty()) {
            return;
        }
        $thread->messages()->whereNotIn('id', $keepIds)->delete();
    }

    /**
     * @param  array<string, mixed>  $result
     */
    private function confirmChatLine(CopilotPendingAction $pending, array $result): string
    {
        $number = $result['invoice_number'] ?? $result['estimate_number'] ?? $result['bill_number'] ?? $result['cn_number'] ?? $result['dn_number'] ?? $result['so_number'] ?? null;
        $status = $result['status'] ?? 'done';
        if ($number) {
            $line = 'Confirmed **'.$number.'** ('.$status.').';
            if (! empty($result['supplier_name'])) {
                $line .= ! empty($result['supplier_created'])
                    ? ' Supplier **'.$result['supplier_name'].'** created and linked.'
                    : ' Linked to supplier **'.$result['supplier_name'].'**.';
            }

            return $line;
        }

        return 'Confirmed: **'.$pending->tool_name.'**.';
    }

    /**
     * @param  array<string, mixed>  $args
     */
    private function summarise(string $name, array $args): string
    {
        $bits = [$name];
        foreach (['invoice_number', 'invoice_id', 'customer_id', 'amount', 'bill_date'] as $key) {
            if (isset($args[$key])) {
                $bits[] = $key.'='.$args[$key];
            }
        }

        return implode(' · ', $bits);
    }

    /**
     * @return array<string, mixed>
     */
    private function pendingPayload(CopilotPendingAction $pending): array
    {
        return [
            'id' => $pending->id,
            'tool_name' => $pending->tool_name,
            'risk' => $pending->risk,
            'summary' => $pending->summary,
            'payload' => $pending->payload,
            'status' => $pending->status,
            'result' => $pending->result,
            'error' => $pending->error,
        ];
    }
}
