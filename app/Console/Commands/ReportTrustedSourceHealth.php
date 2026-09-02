<?php

namespace App\Console\Commands;

use App\Models\ContentSource;
use Illuminate\Console\Command;

class ReportTrustedSourceHealth extends Command
{
    protected $signature = 'question-bank:source-health-report
                            {--hours=24 : Maximum age of the latest health check}
                            {--fail-on-unhealthy : Fail when any active source is unhealthy, stale, or unchecked}';

    protected $description = 'Report the latest health state of every active trusted source';

    public function handle(): int
    {
        $hours = filter_var($this->option('hours'), FILTER_VALIDATE_INT);

        if ($hours === false || $hours < 1 || $hours > 8760) {
            $this->error('The --hours option must be an integer between 1 and 8760.');

            return self::FAILURE;
        }

        $cutoff = now()->subHours($hours);
        $counts = ['healthy' => 0, 'unhealthy' => 0, 'stale' => 0];
        $rows = [];

        $sources = ContentSource::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        foreach ($sources as $source) {
            $check = $source->healthChecks()->latest('checked_at')->first();

            if (! $check || $check->checked_at->lt($cutoff)) {
                $state = 'stale';
                $reason = $check ? 'last check is too old' : 'never checked';
            } elseif ($check->healthy) {
                $state = 'healthy';
                $reason = $check->reason;
            } else {
                $state = 'unhealthy';
                $reason = $check->reason;
            }

            $counts[$state]++;
            $rows[] = [
                $source->name,
                $state,
                $check?->checked_at?->toDateTimeString() ?? 'never',
                $reason,
            ];
        }

        $this->table(['Source', 'State', 'Last check', 'Reason'], $rows);
        $this->info(sprintf(
            'Active sources: %d | Healthy: %d | Unhealthy: %d | Stale/unchecked: %d',
            $sources->count(),
            $counts['healthy'],
            $counts['unhealthy'],
            $counts['stale'],
        ));

        if ($this->option('fail-on-unhealthy') && ($counts['unhealthy'] > 0 || $counts['stale'] > 0)) {
            $this->error('Trusted source health report failed.');

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
