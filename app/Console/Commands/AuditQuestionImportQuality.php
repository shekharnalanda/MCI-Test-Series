<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class AuditQuestionImportQuality extends Command
{
    protected $signature = 'question-bank:audit-import-quality
        {--hours=24 : Completed batches to inspect}
        {--max-rejection-rate=20 : Maximum rejected percentage}
        {--max-duplicate-rate=60 : Maximum duplicate percentage}
        {--strict : Return failure when a threshold is exceeded}';

    protected $description = 'Audit recent question imports for rejection and duplicate spikes';

    public function handle(): int
    {
        $hours = max(1, (int) $this->option('hours'));
        $batches = DB::table('question_import_batches')
            ->whereIn('status', ['completed', 'failed'])
            ->where('completed_at', '>=', now()->subHours($hours))
            ->get();

        if ($batches->isEmpty()) {
            $this->info('No completed or failed question import batches found in the audit window.');

            return self::SUCCESS;
        }

        $received = (int) $batches->sum('received_count');
        $accepted = (int) $batches->sum('accepted_count');
        $duplicates = (int) $batches->sum('duplicate_count');
        $rejected = (int) $batches->sum('rejected_count');
        $failedBatches = $batches->where('status', 'failed')->count();
        $denominator = max(1, $received);
        $duplicateRate = round(($duplicates / $denominator) * 100, 2);
        $rejectionRate = round(($rejected / $denominator) * 100, 2);

        $this->table(
            ['Batches', 'Failed', 'Received', 'Accepted', 'Duplicates', 'Rejected', 'Duplicate %', 'Rejected %'],
            [[$batches->count(), $failedBatches, $received, $accepted, $duplicates, $rejected, $duplicateRate, $rejectionRate]]
        );

        $unhealthy = $failedBatches > 0
            || $duplicateRate > (float) $this->option('max-duplicate-rate')
            || $rejectionRate > (float) $this->option('max-rejection-rate');

        if ($unhealthy) {
            $this->warn('Question import quality thresholds exceeded.');

            return $this->option('strict') ? self::FAILURE : self::SUCCESS;
        }

        $this->info('Question import quality is within configured thresholds.');

        return self::SUCCESS;
    }
}
