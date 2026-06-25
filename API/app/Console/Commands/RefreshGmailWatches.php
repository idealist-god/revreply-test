<?php

namespace App\Console\Commands;

use App\Models\GmailAccount;
use App\Services\GmailWatchService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class RefreshGmailWatches extends Command
{
    protected $signature   = 'gmail:refresh-watches';
    protected $description = 'Renew Gmail Pub/Sub watch subscriptions expiring within 24 hours';

    public function handle(GmailWatchService $watchService): int
    {
        $accounts = GmailAccount::where(function ($query) {
            $query->whereNull('watch_expires_at')
                  ->orWhere('watch_expires_at', '<=', now()->addHours(24));
        })->get();

        if ($accounts->isEmpty()) {
            $this->info('No watches need renewal.');
            return self::SUCCESS;
        }

        $this->info("Renewing {$accounts->count()} watch(es)...");

        $renewed = 0;
        $failed  = 0;

        foreach ($accounts as $account) {
            try {
                $watchService->renewWatch($account);
                $renewed++;
                $this->line("  ✓ {$account->gmail_address}");
            } catch (\Throwable $e) {
                $failed++;
                $this->warn("  ✗ {$account->gmail_address}: {$e->getMessage()}");
                Log::error('gmail:refresh-watches failed for account', [
                    'gmail_account_id' => $account->id,
                    'error'            => $e->getMessage(),
                ]);
            }
        }

        $this->info("Done. Renewed: {$renewed}, Failed: {$failed}.");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
