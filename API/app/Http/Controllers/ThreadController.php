<?php

namespace App\Http\Controllers;

use App\Models\EmailThread;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ThreadController extends Controller
{
    private function accountIds(Request $request): array
    {
        return $request->user()->gmailAccounts()->pluck('id')->all();
    }

    public function index(Request $request): JsonResponse
    {
        $accountIds = $this->accountIds($request);

        $query = EmailThread::whereIn('gmail_account_id', $accountIds)
            ->with(['latestDraft'])
            ->withCount('messages');

        if ($request->filled('classification')) {
            $query->where('classification', $request->input('classification'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('account_id')) {
            $query->where('gmail_account_id', $request->input('account_id'));
        }

        $threads = $query->orderByDesc('last_message_at')->paginate(20);

        return response()->json($threads);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $thread = EmailThread::whereIn('gmail_account_id', $this->accountIds($request))
            ->with(['messages' => fn ($q) => $q->orderBy('received_at'), 'latestDraft'])
            ->findOrFail($id);

        return response()->json($thread);
    }

    public function dismiss(Request $request, int $id): JsonResponse
    {
        $thread = EmailThread::whereIn('gmail_account_id', $this->accountIds($request))
            ->findOrFail($id);

        $thread->update(['status' => 'dismissed']);

        return response()->json(['message' => 'Thread dismissed.']);
    }
}
