<?php

namespace App\Console\Commands;

use App\Services\CurrentAffairsMaintenanceService;
use Illuminate\Console\Command;

class MaintainCurrentAffairs extends Command
{
    protected $signature =
        'mci:current-affairs-maintain';

    protected $description =
        'Refresh current affairs freshness scores and expire stale items';

    public function handle(
        CurrentAffairsMaintenanceService $service
    ): int {
        $refreshed =
            $service->refreshQuestionFreshness();

        $expired =
            $service->expireOldItems();

        $this->info(
            "Current Affairs Maintenance Complete"
        );

        $this->line(
            "Questions refreshed: {$refreshed}"
        );

        $this->line(
            "Items expired: {$expired}"
        );

        return self::SUCCESS;
    }
}
