<?php

namespace App\Http\Controllers;

use App\Models\GmailAccount;
use App\Services\GoogleOAuthService;
use App\Services\GmailWatchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;

class GoogleAuthController extends Controller
{
    public function __construct(
        private GoogleOAuthService $oauthService,
        private GmailWatchService $watchService,
    ) {}

    public function redirect(Request $request): JsonResponse
    {
        $state = Crypt::encryptString((string) $request->user()->id);

        $url = $this->oauthService->getAuthUrl($state);

        return response()->json(['url' => $url]);
    }

    public function callback(Request $request): \Illuminate\Http\RedirectResponse
    {
        if ($request->has('error')) {
            Log::warning('Google OAuth denied', ['error' => $request->get('error')]);
            return redirect(config('app.frontend_url') . '/dashboard?error=google_denied');
        }

        $userId       = Crypt::decryptString($request->get('state'));
        $token        = $this->oauthService->exchangeCode($request->get('code'));
        $gmailAddress = $this->oauthService->getUserEmail();

        $account = GmailAccount::updateOrCreate(
            ['gmail_address' => $gmailAddress],
            [
                'user_id'          => $userId,
                'access_token'     => $token['access_token'],
                'refresh_token'    => $token['refresh_token'] ?? null,
                'token_expires_at' => now()->addSeconds($token['expires_in']),
            ]
        );

        try {
            $this->watchService->startWatch($account);
        } catch (\Throwable $e) {
            Log::warning('GmailWatch setup failed — account connected without push notifications', [
                'gmail_account_id' => $account->id,
                'error'            => $e->getMessage(),
            ]);
        }

        return redirect(config('app.frontend_url', config('app.url')) . '/dashboard?connected=1');
    }
}
