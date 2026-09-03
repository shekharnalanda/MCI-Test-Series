<?php

namespace App\Services;

use App\Models\ContentSource;
use App\Models\Question;
use App\Models\QuestionImportBatch;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class QuestionIngestionService
{
    public function __construct(
        private QuestionFingerprintService $fingerprints,
        private readonly TrustedSourcePolicy $sourcePolicy
    ) {}

    public function ingest(
        array $items,
        ?ContentSource $source = null,
        string $batchType = 'manual'
    ): QuestionImportBatch {

        $batch = QuestionImportBatch::create([
            'content_source_id' => $source?->id,
            'batch_code' => 'QB-'.now()->format('YmdHis').'-'.Str::upper(Str::random(6)),
            'batch_type' => $batchType,
            'received_count' => count($items),
            'status' => 'processing',
            'started_at' => now(),
        ]);

        $accepted = 0;
        $duplicates = 0;
        $rejected = 0;

        foreach ($items as $item) {

            try {

                DB::transaction(function () use (
                    $item,
                    $source,
                    $batch,
                    &$accepted,
                    &$duplicates,
                    &$rejected
                ) {

                    $text = trim((string) ($item['question_text'] ?? ''));

                    if ($text === '') {
                        $rejected++;
                        return;
                    }

                    $normalizedHash = $this->fingerprints->hash($text);

                    if (
                        Question::where(
                            'normalized_hash',
                            $normalizedHash
                        )->exists()
                    ) {
                        $duplicates++;
                        return;
                    }

                    $options = $item['options'] ?? [];

                    if (count($options) < 2) {
                        $rejected++;
                        return;
                    }

                    $correctCount = collect($options)
                        ->where('is_correct', true)
                        ->count();

                    if ($correctCount !== 1) {
                        $rejected++;
                        return;
                    }

                    $trust = (int) ($source?->trust_score ?? 50);

                    $quality = $this->qualityScore($item);

                    $canAutoPublish =
                        $source &&
                $this->sourcePolicy->canAutoPublishQuestions($source) &&
                        $source->auto_publish_allowed &&
                        $trust >= 90 &&
                        $quality >= 90;

                    $question = Question::create([
                        'subject_id' => $item['subject_id'] ?? null,
                        'topic_id' => $item['topic_id'] ?? null,

                        'content_source_id' => $source?->id,
                    'source_url' => $item['source_url'] ?? null,
                    'source_reference' => $item['source_reference'] ?? null,
                    'source_published_at' => $item['source_published_at'] ?? null,
                    'imported_at' => now(),
                        'question_import_batch_id' => $batch->id,

                        'question_text' => $text,
                        'question_text_hi' => $item['question_text_hi'] ?? null,

                        'explanation' => $item['explanation'] ?? null,
                        'explanation_hi' => $item['explanation_hi'] ?? null,

                        'question_type' => 'single_choice',
                        'difficulty' => $item['difficulty'] ?? 'medium',
                        'language' => $item['language'] ?? 'bilingual',

                        'is_current_affairs' =>
                            (bool) ($item['is_current_affairs'] ?? false),

                        'current_affair_date' =>
                            $item['current_affair_date'] ?? null,

                        'source_name' =>
                            $source?->name ??
                            ($item['source_name'] ?? 'Internal'),


                        'source_confidence' => $trust,

                        'verification_status' =>
                            $canAutoPublish
                                ? 'verified'
                                : 'pending',

                        'generation_method' =>
                            $item['generation_method'] ?? 'import',

                        'content_hash' => hash(
                            'sha256',
                            $text.'|'.json_encode($options)
                        ),

                        'normalized_hash' => $normalizedHash,

                        'quality_score' => $quality,

                        'freshness_score' =>
                            $this->freshnessScore($item),

                        'auto_publish' => $canAutoPublish,
                        'is_published' => $canAutoPublish,
                        'published_at' =>
                            $canAutoPublish ? now() : null,
                        'verified_at' =>
                            $canAutoPublish ? now() : null,

                        'is_active' => true,
                    ]);

                    foreach ($options as $index => $option) {

                        $question->options()->create([
                            'option_text' =>
                                $option['option_text'],

                            'option_text_hi' =>
                                $option['option_text_hi'] ?? null,

                            'is_correct' =>
                                (bool) $option['is_correct'],

                            'sort_order' => $index + 1,
                        ]);
                    }

                    if (!empty($item['exam_ids'])) {
                        $question->exams()->syncWithoutDetaching(
                            collect($item['exam_ids'])
                                ->mapWithKeys(
                                    fn ($id) => [
                                        $id => ['relevance_score' => 100]
                                    ]
                                )
                                ->all()
                        );
                    }

                    $accepted++;
                });

            } catch (\Throwable $e) {
                $rejected++;
            }
        }

        $batch->update([
            'accepted_count' => $accepted,
            'duplicate_count' => $duplicates,
            'rejected_count' => $rejected,
            'status' => 'completed',
            'completed_at' => now(),
        ]);

        return $batch->fresh();
    }

    private function qualityScore(array $item): int
    {
        $score = 40;

        if (!empty($item['question_text_hi'])) {
            $score += 10;
        }

        if (!empty($item['explanation'])) {
            $score += 15;
        }

        if (!empty($item['subject_id'])) {
            $score += 10;
        }

        if (!empty($item['exam_ids'])) {
            $score += 10;
        }

        if (count($item['options'] ?? []) >= 4) {
            $score += 15;
        }

        return min(100, $score);
    }

    private function freshnessScore(array $item): int
    {
        if (empty($item['is_current_affairs'])) {
            return 100;
        }

        if (empty($item['current_affair_date'])) {
            return 25;
        }

        $days = now()
            ->startOfDay()
            ->diffInDays(
                \Carbon\Carbon::parse(
                    $item['current_affair_date']
                )->startOfDay(),
                true
            );

        return match (true) {
            $days <= 7 => 100,
            $days <= 30 => 90,
            $days <= 90 => 70,
            $days <= 180 => 50,
            default => 25,
        };
    }
}
