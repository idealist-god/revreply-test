<?php

namespace App\Http\Controllers;

use App\Models\GmailAccount;
use App\Services\GoogleOAuthService;
use App\Services\GmailWatchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Google\Service\Oauth2 as GoogleOauth2;

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

        $userId = Crypt::decryptString($request->get('state'));
        $token  = $this->oauthService->exchangeCode($request->get('code'));

        $client      = $this->oauthService->getAuthenticatedClientFromToken($token);
        $oauth2      = new GoogleOauth2($client);
        $userInfo    = $oauth2->userinfo->get();
        $gmailAddress = $userInfo->getEmail();

        $account = GmailAccount::updateOrCreate(
            ['gmail_address' => $gmailAddress],
            [
                'user_id'          => $userId,
                'access_token'     => $token['access_token'],
                'refresh_token'    => $token['refresh_token'] ?? null,
                'token_expires_at' => now()->addSeconds($token['expires_in']),
            ]
        );

        $this->watchService->startWatch($account);

        return redirect(config('app.frontend_url') . '/dashboard?connected=1');
    }
}
