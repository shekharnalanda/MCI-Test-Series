<?php

namespace App\Services;

use App\Models\ContentSource;
use RuntimeException;

class BulkQuestionImportService
{
    public function __construct(
        private QuestionIngestionService $ingestion
    ) {}

    public function importJsonFile(
        string $path,
        ContentSource $source,
        int $chunkSize = 500
    ): array {
        if (!is_file($path)) {
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
