<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessGmailHistoryJob;
use App\Models\GmailAccount;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class GmailWebhookController extends Controller
{
    public function handle(Request $request): Response
    {
        try {
            $body = $request->input('message');

            if (empty($body['data'])) {
                Log::warning('Gmail webhook: missing message data');
                return response('', 200);
            }

            $decoded   = json_decode(base64_decode($body['data']), true);
            $email     = $decoded['emailAddress'] ?? null;
            $historyId = $decoded['historyId']     ?? null;

            if (! $email || ! $historyId) {
                Log::warning('Gmail webhook: missing emailAddress or historyId', $decoded ?? []);
                return response('', 200);
            }

            $account = GmailAccount::where('gmail_address', $email)->first();

            if (! $account) {
                Log::info('Gmail webhook: no account found for email', ['email' => $email]);
                return response('', 200);
            }

            ProcessGmailHistoryJob::dispatch($account, (string) $historyId)
                ->onQueue('gmail-sync');

        } catch (\Throwable $e) {
            Log::error('Gmail webhook: unexpected error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }

        return response('', 200);
    }
}
