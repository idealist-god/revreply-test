<?php

namespace App\Http\Controllers;

use App\Models\GmailAccount;
use App\Services\GmailWatchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class GmailAccountController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $accounts = $request->user()->gmailAccounts()->get(['id', 'gmail_address', 'watch_expires_at', 'created_at']);

        return response()->json($accounts);
    }

    public function destroy(Request $request, int $id, GmailWatchService $watchService): JsonResponse
    {
        $account = GmailAccount::where('id', $id)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        try {
            $watchService->stopWatch($account);
        } catch (\Throwable $e) {
            Log::warning('Could not stop Gmail watch on disconnect', [
                'gmail_account_id' => $account->id,
                'error'            => $e->getMessage(),
            ]);
        }

        $account->delete();

        return response()->json(['message' => 'Account disconnected.']);
    }
}
