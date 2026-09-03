<?php

namespace App\Console\Commands;

use App\Models\QuestionImportBatch;
use App\Services\BulkQuestionImportService;
use Illuminate\Console\Command;
use Throwable;

class RetryFailedQuestionImport extends Command
{
    protected $signature = 'question-bank:retry-import
                            {batch : Failed import batch code}
                            {--file= : Override JSON file path}
                            {--chunk=500 : Questions per ingestion batch}';

    protected $description = 'Retry a failed question import after revalidating its trusted source';

    public function handle(BulkQuestionImportService $service): int
    {
        $batch = QuestionImportBatch::with('source')
            ->where('batch_code', (string) $this->argument('batch'))
            ->first();

        if (! $batch) {
            $this->error('Question import batch not found.');

            return self::FAILURE;
        }

        if ($batch->status !== 'failed') {
            $this->error('Only failed question import batches can be retried.');

            return self::FAILURE;
        }

        if (! $batch->source || ! $batch->source->is_active) {
            $this->error('The original content source is missing or inactive.');

            return self::FAILURE;
        }

        $path = (string) ($this->option('file') ?: data_get($batch->metadata, 'file'));

        if ($path === '' || ! is_file($path)) {
            $this->error('Retry JSON file was not found. Use --file to provide it.');

            return self::FAILURE;
        }

        $chunk = max(1, (int) $this->option('chunk'));

        try {
            $result = $service->importJsonFile($path, $batch->source, $chunk);

            $batch->update([
                'received_count' => $result['received'] ?? $batch->received_count,
                'accepted_count' => $result['accepted'] ?? $batch->accepted_count,
                'duplicate_count' => $result['duplicates'] ?? $batch->duplicate_count,
                'rejected_count' => $result['rejected'] ?? $batch->rejected_count,
                'status' => 'completed',
                'error_message' => null,
                'completed_at' => now(),
                'metadata' => array_merge($batch->metadata ?? [], [
                    'last_retry_at' => now()->toIso8601String(),
                    'last_retry_status' => 'completed',
                    'retry_file' => $path,
                    'retry_chunk_size' => $chunk,
                ]),
            ]);
        } catch (Throwable $exception) {
            $batch->update([
                'metadata' => array_merge($batch->metadata ?? [], [
                    'last_retry_at' => now()->toIso8601String(),
                    'last_retry_status' => 'failed',
                    'last_retry_error' => $exception->getMessage(),
                    'retry_file' => $path,
                    'retry_chunk_size' => $chunk,
                ]),
            ]);

            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info('Question import retry completed.');

        foreach ($result as $key => $value) {
            $this->line(ucfirst($key).": {$value}");
        }

        return self::SUCCESS;
    }
}
