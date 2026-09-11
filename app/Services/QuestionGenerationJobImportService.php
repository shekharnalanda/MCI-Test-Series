<?php

namespace App\Services;

use App\Models\ContentSource;
use App\Models\QuestionGenerationJob;
use Carbon\Carbon;
use RuntimeException;

class QuestionGenerationJobImportService
{
    public function __construct(
        private readonly QuestionIngestionService $ingestion,
        private readonly TrustedSourcePolicy $sourcePolicy
    ) {}

    public function importJsonFile(
        string $jobCode,
        string $path,
        ContentSource $source,
        int $chunkSize = 500
    ): array {
        $job = QuestionGenerationJob::query()
            ->where('job_code', $jobCode)
            ->firstOrFail();

        if (! in_array($job->status, ['pending', 'partial', 'processing'], true)) {
            throw new RuntimeException('Generation job is not open for imports.');
        }

        if (! $job->exam_id || ! $job->subject_id) {
            throw new RuntimeException('Generation job must be linked to an exam and subject.');
        }

        if (! $this->sourcePolicy->canGenerateQuestions($source)) {
            throw new RuntimeException('Source is not approved by the MCI trusted-source policy.');
        }

        if (! is_file($path)) {
            throw new RuntimeException("Import file not found: {$path}");
        }

        if ($chunkSize < 1 || $chunkSize > 5000) {
            throw new RuntimeException('Chunk size must be between 1 and 5000.');
        }

        $items = json_decode(
            (string) file_get_contents($path),
            true,
            512,
            JSON_THROW_ON_ERROR
        );

        if (! is_array($items) || ! array_is_list($items)) {
            throw new RuntimeException('JSON root must be a list of questions.');
        }

        $prepared = [];
        $validationRejected = 0;

        foreach ($items as $item) {
            if (! is_array($item) || ! $this->passesQualityGate($item)) {
                $validationRejected++;

                continue;
            }

            $item['subject_id'] = $job->subject_id;
            $item['topic_id'] = $item['topic_id'] ?? $job->topic_id;
            $item['exam_ids'] = [$job->exam_id];
            $item['language'] = 'bilingual';
            $item['generation_method'] = 'ai_assisted';

            $prepared[] = $item;
        }

        $job->update([
            'status' => 'processing',
            'started_at' => $job->started_at ?? now(),
            'error_message' => null,
        ]);

        $accepted = 0;
        $duplicates = 0;
        $rejected = $validationRejected;
        $batches = 0;

        try {
            foreach (array_chunk($prepared, $chunkSize) as $chunk) {
                $batch = $this->ingestion->ingest(
                    $chunk,
                    $source,
                    'generated'
                );

                $accepted += (int) $batch->accepted_count;
                $duplicates += (int) $batch->duplicate_count;
                $rejected += (int) $batch->rejected_count;
                $batches++;
            }

            $job->increment('generated_count', count($items));
            $job->increment('accepted_count', $accepted);
            $job->increment('duplicate_count', $duplicates);
            $job->increment('rejected_count', $rejected);
            $job->refresh();

            $completed = $job->accepted_count >= $job->target_count;

            $job->update([
                'status' => $completed ? 'completed' : 'partial',
                'completed_at' => $completed ? now() : null,
            ]);
        } catch (\Throwable $exception) {
            $job->update([
                'status' => $job->accepted_count > 0 ? 'partial' : 'failed',
                'error_message' => $exception->getMessage(),
            ]);

            throw $exception;
        }

        return [
            'job_code' => $job->job_code,
            'received' => count($items),
            'accepted' => $accepted,
            'duplicates' => $duplicates,
            'rejected' => $rejected,
            'batches' => $batches,
            'job_accepted' => (int) $job->fresh()->accepted_count,
            'job_target' => (int) $job->target_count,
            'status' => $job->fresh()->status,
        ];
    }

    private function passesQualityGate(array $item): bool
    {
        foreach ([
            'question_text',
            'question_text_hi',
            'explanation',
            'explanation_hi',
            'source_url',
            'source_reference',
            'source_published_at',
        ] as $field) {
            if (! filled($item[$field] ?? null)) {
                return false;
            }
        }

        $url = (string) $item['source_url'];

        if (
            filter_var($url, FILTER_VALIDATE_URL) === false
            || strtolower((string) parse_url($url, PHP_URL_SCHEME)) !== 'https'
        ) {
            return false;
        }

        try {
            if (Carbon::parse($item['source_published_at'])->isFuture()) {
                return false;
            }
        } catch (\Throwable) {
            return false;
        }

        if (! in_array($item['difficulty'] ?? 'medium', ['easy', 'medium', 'hard'], true)) {
            return false;
        }

        $options = $item['options'] ?? [];

        if (! is_array($options) || count($options) !== 4) {
            return false;
        }

        $english = [];
        $hindi = [];
        $correct = 0;

        foreach ($options as $option) {
            if (
                ! is_array($option)
                || ! filled($option['option_text'] ?? null)
                || ! filled($option['option_text_hi'] ?? null)
            ) {
                return false;
            }

            $english[] = mb_strtolower(trim((string) $option['option_text']));
            $hindi[] = mb_strtolower(trim((string) $option['option_text_hi']));
            $correct += (bool) ($option['is_correct'] ?? false) ? 1 : 0;
        }

        return count(array_unique($english)) === 4
            && count(array_unique($hindi)) === 4
            && $correct === 1;
    }
}
