<?php

namespace App\Services;

use App\Models\GmailAccount;
use Google\Client;
use Illuminate\Support\Facades\Log;

class GoogleOAuthService
{
    private Client $client;

    public function __construct()
    {
        $this->client = new Client();
        $this->client->setClientId(config('services.google.client_id'));
        $this->client->setClientSecret(config('services.google.client_secret'));
        $this->client->setRedirectUri(config('services.google.redirect_uri'));
        $this->client->setScopes([
            'https://www.googleapis.com/auth/gmail.readonly',
            'https://www.googleapis.com/auth/gmail.compose',
            'https://www.googleapis.com/auth/gmail.send',
            'https://www.googleapis.com/auth/gmail.labels',
        ]);
        $this->client->setAccessType('offline');
        $this->client->setPrompt('consent');
    }

    public function getAuthUrl(string $state = ''): string
    {
        if ($state !== '') {
            $this->client->setState($state);
        }

        return $this->client->createAuthUrl();
    }

    public function getAuthenticatedClientFromToken(array $token): Client
    {
        $client = new Client();
        $client->setClientId(config('services.google.client_id'));
        $client->setClientSecret(config('services.google.client_secret'));
        $client->setAccessToken($token);

        return $client;
    }

    public function exchangeCode(string $code): array
    {
        $token = $this->client->fetchAccessTokenWithAuthCode($code);

        if (isset($token['error'])) {
            throw new \RuntimeException('Google token exchange failed: ' . $token['error_description'] ?? $token['error']);
        }

        return $token;
    }

    public function getAuthenticatedClient(GmailAccount $account): Client
    {
        $expiresAt = $account->token_expires_at;

        if ($expiresAt && now()->addMinutes(5)->greaterThan($expiresAt)) {
            $this->refreshToken($account);
            $account->refresh();
        }

        $client = new Client();
        $client->setClientId(config('services.google.client_id'));
        $client->setClientSecret(config('services.google.client_secret'));
        $client->setAccessToken([
            'access_token'  => $account->access_token,
            'refresh_token' => $account->refresh_token,
            'expires_in'    => now()->diffInSeconds($account->token_expires_at),
        ]);

        return $client;
    }

    public function refreshToken(GmailAccount $account): void
    {
        $this->client->setAccessToken([
            'access_token'  => $account->access_token,
            'refresh_token' => $account->refresh_token,
        ]);

        $newToken = $this->client->fetchAccessTokenWithRefreshToken($account->refresh_token);

        if (isset($newToken['error'])) {
            Log::error('Google token refresh failed', [
                'gmail_account_id' => $account->id,
                'gmail_address'    => $account->gmail_address,
                'error'            => $newToken['error'],
                'error_description' => $newToken['error_description'] ?? null,
            ]);

            throw new \RuntimeException('Google token refresh failed: ' . ($newToken['error_description'] ?? $newToken['error']));
        }

        $account->update([
            'access_token'     => $newToken['access_token'],
            'token_expires_at' => now()->addSeconds($newToken['expires_in']),
        ]);
    }
}
