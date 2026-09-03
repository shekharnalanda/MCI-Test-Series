<?php

namespace App\Services;

use App\Models\ContentSource;
use App\Models\QuestionImportBatch;
use Illuminate\Support\Str;
use RuntimeException;

class BulkQuestionImportService
{
    public function __construct(
        private QuestionIngestionService $ingestion,
        private TrustedSourcePolicy $sourcePolicy
    ) {}

    public function importJsonFile(
        string $path,
        ContentSource $source,
        int $chunkSize = 500
    ): array {
        if (! $this->sourcePolicy->canGenerateQuestions($source)) {
            throw new RuntimeException(
                'Source is not approved by the MCI trusted-source policy.'
            );
        }

        if (! is_file($path)) {
            throw new RuntimeException(
                "Import file not found: {$path}"
            );
        }

        if ($chunkSize < 1 || $chunkSize > 5000) {
            throw new RuntimeException(
                'Chunk size must be between 1 and 5000.'
            );
        }

        $raw = file_get_contents($path);

        if ($raw === false) {
            throw new RuntimeException(
                'Unable to read import file.'
            );
        }

        $items = json_decode(
            $raw,
            true,
            512,
            JSON_THROW_ON_ERROR
        );

        if (!is_array($items)) {
            throw new RuntimeException(
                'JSON root must be an array of questions.'
            );
        }

        $totals = [
            'received' => count($items),
            'accepted' => 0,
            'duplicates' => 0,
            'rejected' => 0,
            'batches' => 0,
        ];

        foreach (
            array_chunk($items, $chunkSize)
            as $chunk
        ) {
            $freshSource = $source->fresh() ?? $source;

            if (! $this->sourcePolicy->canGenerateQuestions($freshSource)) {
                $message = 'Source is no longer approved by the MCI trusted-source policy.';

                QuestionImportBatch::create([
                    'content_source_id' => $source->id,
                    'batch_code' => 'QB-'.now()->format('YmdHis').'-'.Str::upper(Str::random(6)),
                    'batch_type' => 'json',
                    'received_count' => count($chunk),
                    'accepted_count' => 0,
                    'duplicate_count' => 0,
                    'rejected_count' => count($chunk),
                    'status' => 'failed',
                    'started_at' => now(),
                    'completed_at' => now(),
                    'error_message' => $message,
                    'metadata' => ['file' => basename($path), 'chunk_size' => count($chunk)],
                ]);
                throw new RuntimeException($message);
            }

            $batch = $this->ingestion->ingest(
                $chunk,
                $source,
                'json'
            );

            $totals['accepted'] +=
                $batch->accepted_count;

            $totals['duplicates'] +=
                $batch->duplicate_count;

            $totals['rejected'] +=
                $batch->rejected_count;

            $totals['batches']++;
        }

        return $totals;
    }
}
