<?php

namespace App\Console\Commands;

use App\Models\QuestionImportBatch;
use Illuminate\Console\Command;

class ReportFailedQuestionImports extends Command
{
    protected $signature = 'question-bank:failed-imports
        {--hours=168 : Failure age window in hours}
        {--limit=50 : Maximum batches to display}
        {--strict : Return failure when failed batches exist}';

    protected $description = 'List failed question import batches for review and recovery';

    public function handle(): int
    {
        $hours = max(1, (int) $this->option('hours'));
        $limit = min(500, max(1, (int) $this->option('limit')));

        $batches = QuestionImportBatch::query()
            ->with('source:id,name')
            ->where('status', 'failed')
            ->where('updated_at', '>=', now()->subHours($hours))
            ->latest('updated_at')
            ->limit($limit)
            ->get();

        if ($batches->isEmpty()) {
            $this->info('No failed question import batches found.');

            return self::SUCCESS;
        }

        $this->warn("Failed question import batches: {$batches->count()}");

        $this->table(
            ['Batch', 'Source', 'Type', 'Received', 'Accepted', 'Duplicates', 'Rejected', 'Failed at', 'Error'],
            $batches->map(fn (QuestionImportBatch $batch): array => [
                $batch->batch_code,
                $batch->source?->name ?? 'Unknown',
                $batch->batch_type,
                $batch->received_count,
                $batch->accepted_count,
                $batch->duplicate_count,
                $batch->rejected_count,
                optional($batch->completed_at ?? $batch->updated_at)->toDateTimeString(),
                $batch->error_message ?: 'Not recorded',
            ])->all()
        );

        return $this->option('strict') ? self::FAILURE : self::SUCCESS;
    }
}
