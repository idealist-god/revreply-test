<?php

namespace App\Console\Commands;

use App\Jobs\FetchAndClassifyEmailJob;
use App\Models\GmailAccount;
use App\Services\GmailMessageService;
use App\Services\GoogleOAuthService;
use Google\Service\Gmail;
use Illuminate\Console\Command;

class SyncGmail extends Command
{
    protected $signature = 'gmail:sync {--account= : Sync a specific GmailAccount ID}';
    protected $description = 'Manually pull new Gmail messages and dispatch classify/draft jobs';

    public function handle(GmailMessageService $messageService, GoogleOAuthService $oauthService): int
    {
        $query = GmailAccount::query();

        if ($id = $this->option('account')) {
            $query->where('id', $id);
        }

        $accounts = $query->get();

        if ($accounts->isEmpty()) {
            $this->error('No Gmail accounts found.');
            return self::FAILURE;
        }

        foreach ($accounts as $account) {
            $this->info("Syncing {$account->gmail_address}…");

            try {
                $messageIds = $this->resolveMessageIds($account, $messageService, $oauthService);
            } catch (\Throwable $e) {
                $this->error("  Failed: {$e->getMessage()}");
                continue;
            }

            if (empty($messageIds)) {
                $this->line('  No new messages.');
                continue;
            }

            foreach ($messageIds as $msgId) {
                FetchAndClassifyEmailJob::dispatch($account, $msgId)->onQueue('classify');
            }

            $this->info('  Dispatched ' . count($messageIds) . ' message(s) to classify queue.');
        }

        return self::SUCCESS;
    }

    private function resolveMessageIds(
        GmailAccount $account,
        GmailMessageService $messageService,
        GoogleOAuthService $oauthService,
    ): array {
        $client = $oauthService->getAuthenticatedClient($account);
        $gmail  = new Gmail($client);

        // If we have a history cursor, use incremental history.list
        if ($account->watch_history_id) {
            $ids = $messageService->fetchNewMessageIds($account, $account->watch_history_id);
            $account->update(['watch_history_id' => $gmail->users->getProfile('me')->getHistoryId()]);
            return $ids;
        }

        // First-time sync: list all recent inbox messages
        $profile  = $gmail->users->getProfile('me');
        $response = $gmail->users_messages->listUsersMessages('me', [
            'labelIds'   => ['INBOX'],
            'maxResults' => 100,
        ]);

        $account->update(['watch_history_id' => $profile->getHistoryId()]);

        return array_map(fn($m) => $m->getId(), $response->getMessages() ?? []);
    }
}
