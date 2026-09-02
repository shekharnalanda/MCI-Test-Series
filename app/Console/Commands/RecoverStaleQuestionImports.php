<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RecoverStaleQuestionImports extends Command
{
    protected $signature = 'question-bank:recover-imports
        {--stale-minutes=30 : Processing age before a batch is considered stale}
        {--dry-run : Report stale batches without changing them}';

    protected $description = 'Safely recover question import batches left in processing state';

    public function handle(): int
    {
        $minutes = max(1, (int) $this->option('stale-minutes'));
        $cutoff = now()->subMinutes($minutes);

        $query = DB::table('question_import_batches')
            ->where('status', 'processing')
            ->whereNotNull('started_at')
            ->where('started_at', '<=', $cutoff);

        $count = (clone $query)->count();

        if ($count === 0) {
            $this->info('No stale question import batches found.');

            return self::SUCCESS;
        }

        if ($this->option('dry-run')) {
            $this->warn("Dry run: {$count} stale question import batch(es) found.");

            return self::SUCCESS;
        }

        DB::transaction(function () use ($query, $minutes): void {
            $query->update([
                'status' => 'failed',
                'error_message' => "Automatically recovered after remaining in processing state for more than {$minutes} minutes.",
                'completed_at' => now(),
                'updated_at' => now(),
            ]);
        });

        $this->info("Recovered {$count} stale question import batch(es).");

        return self::SUCCESS;
    }
}
