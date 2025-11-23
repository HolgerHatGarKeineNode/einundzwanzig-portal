<?php

namespace App\Console\Commands\Nostr;

use App\Jobs\FetchNostrProfileJob;
use Illuminate\Console\Command;

class SyncNostrProfilesCommand extends Command
{
    protected $signature = 'nostr:sync-nostr-profiles';

    protected $description = 'Command description';

    public function handle(): void
    {
        FetchNostrProfileJob::dispatch();
    }
}
