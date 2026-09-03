<?php

namespace App\Console\Commands;

use App\Models\QuestionImportBatch;
use Illuminate\Console\Command;

class RetryFailedQuestionImportQueue extends Command
{
    protected $signature = 'question-bank:retry-imports
                            {--limit=10 : Maximum failed batches to inspect}
                            {--chunk=500 : Questions per ingestion batch}
                            {--strict : Fail when any selected batch remains unresolved}';

    protected $description = 'Automatically retry eligible failed question import batches';

    public function handle(): int
    {
        $limit = min(100, max(1, (int) $this->option('limit')));
        $chunk = min(5000, max(1, (int) $this->option('chunk')));

        $batches = QuestionImportBatch::with('source')
            ->where('status', 'failed')
            ->oldest('completed_at')
            ->limit($limit)
            ->get();

        if ($batches->isEmpty()) {
            $this->info('No failed question import batches found.');

            return self::SUCCESS;
        }

        $completed = 0;
        $skipped = 0;
        $failed = 0;

        foreach ($batches as $batch) {
            $path = (string) data_get($batch->metadata, 'file');

            if (! $batch->source || ! $batch->source->is_active || $path === '' || ! is_file($path)) {
                $skipped++;
                $this->warn("Skipped {$batch->batch_code}: source inactive or retry file unavailable.");
                continue;
            }

            $exitCode = $this->call('question-bank:retry-import', [
                'batch' => $batch->batch_code,
                '--chunk' => $chunk,
            ]);

            if ($exitCode === self::SUCCESS) {
                $completed++;
            } else {
                $failed++;
            }
        }

        $this->info("Retry queue: selected={$batches->count()} completed={$completed} skipped={$skipped} failed={$failed}");

        return $this->option('strict') && ($skipped + $failed > 0)
            ? self::FAILURE
            : self::SUCCESS;
    }
}
