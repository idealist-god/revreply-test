<?php

namespace App\Http\Controllers;

use App\Jobs\GenerateDraftJob;
use App\Models\DraftReply;
use App\Services\GoogleOAuthService;
use Google\Service\Gmail;
use Google\Service\Gmail\Draft;
use Google\Service\Gmail\Message;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class DraftController extends Controller
{
    private function findDraft(Request $request, int $id): DraftReply
    {
        $accountIds = $request->user()->gmailAccounts()->pluck('id')->all();

        return DraftReply::whereHas('thread', fn ($q) => $q->whereIn('gmail_account_id', $accountIds))
            ->findOrFail($id);
    }

    public function update(Request $request, int $id, GoogleOAuthService $oauthService): JsonResponse
    {
        $data  = $request->validate(['body' => ['required', 'string']]);
        $draft = $this->findDraft($request, $id);

        $draft->update(['body' => $data['body']]);

        try {
            $account = $draft->thread->gmailAccount;
            $client  = $oauthService->getAuthenticatedClient($account);
            $service = new Gmail($client);

            $message = new Message();
            $message->setRaw($this->encodeBody($data['body']));

            $gmailDraft = new Draft();
            $gmailDraft->setId($draft->gmail_draft_id);
            $gmailDraft->setMessage($message);

            $service->users_drafts->update('me', $draft->gmail_draft_id, $gmailDraft);
        } catch (\Throwable $e) {
            Log::warning('DraftController: Gmail draft sync failed', [
                'draft_id' => $draft->id,
                'error'    => $e->getMessage(),
            ]);
        }

        return response()->json($draft->fresh());
    }

    public function approve(Request $request, int $id, GoogleOAuthService $oauthService): JsonResponse
    {
        $draft   = $this->findDraft($request, $id);
        $account = $draft->thread->gmailAccount;
        $client  = $oauthService->getAuthenticatedClient($account);
        $service = new Gmail($client);

        $service->users_drafts->send('me', new Draft(['id' => $draft->gmail_draft_id]));

        $draft->update(['status' => 'sent', 'sent_at' => now()]);
        $draft->thread->update(['status' => 'sent']);

        return response()->json(['message' => 'Draft sent.']);
    }

    public function reject(Request $request, int $id, GoogleOAuthService $oauthService): JsonResponse
    {
        $draft = $this->findDraft($request, $id);

        try {
            $account = $draft->thread->gmailAccount;
            $client  = $oauthService->getAuthenticatedClient($account);
            $service = new Gmail($client);
            $service->users_drafts->delete('me', $draft->gmail_draft_id);
        } catch (\Throwable $e) {
            Log::warning('DraftController: Gmail draft delete failed', [
                'draft_id' => $draft->id,
                'error'    => $e->getMessage(),
            ]);
        }

        $draft->update(['status' => 'rejected']);
        $draft->thread->update(['status' => 'dismissed']);

        return response()->json(['message' => 'Draft rejected.']);
    }

    public function regenerate(Request $request, int $id): JsonResponse
    {
        $draft = $this->findDraft($request, $id);

        $draft->update(['status' => 'rejected']);

        GenerateDraftJob::dispatch($draft->message)->onQueue('draft');

        return response()->json(['message' => 'Regeneration queued.']);
    }

    private function encodeBody(string $body): string
    {
        return rtrim(strtr(base64_encode($body), '+/', '-_'), '=');
    }
}
