<?php

namespace App\Console\Commands;

use App\Models\ContentSourceCheck;
use Illuminate\Console\Command;

class PruneTrustedSourceChecks extends Command
{
    protected $signature = 'question-bank:prune-source-checks
                            {--days=90 : Retain checks from this many recent days}
                            {--dry-run : Report eligible rows without deleting them}';

    protected $description = 'Prune old trusted source health-check history';

    public function handle(): int
    {
        $days = filter_var($this->option('days'), FILTER_VALIDATE_INT);

        if ($days === false || $days < 1 || $days > 3650) {
            $this->error('The --days option must be an integer between 1 and 3650.');

            return self::FAILURE;
        }

        $cutoff = now()->subDays($days);
        $query = ContentSourceCheck::query()->where('checked_at', '<', $cutoff);
        $count = (clone $query)->count();

        if ($this->option('dry-run')) {
            $this->info("Dry run: {$count} health check(s) older than {$days} day(s) would be deleted.");

            return self::SUCCESS;
        }

        $deleted = $query->delete();

        $this->info("Deleted {$deleted} trusted source health check(s); retained the latest {$days} day(s).");

        return self::SUCCESS;
    }
}
