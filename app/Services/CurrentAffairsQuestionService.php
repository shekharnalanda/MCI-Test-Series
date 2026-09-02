<?php

namespace App\Services;

use App\Models\CurrentAffairItem;
use App\Models\Exam;
use App\Models\Subject;
use RuntimeException;

class CurrentAffairsQuestionService
{
    public function __construct(
        private QuestionIngestionService $ingestion
    ) {}

    public function createQuestion(
        CurrentAffairItem $item,
        array $questionData
    ): void {
        if ($item->status !== 'approved') {
            throw new RuntimeException(
                'Current affair item must be approved first.'
            );
        }

        if ($item->question_generated) {
            throw new RuntimeException(
                'Question already generated for this item.'
            );
        }

        $subject = Subject::where(
            'name',
            'Current Affairs'
        )->firstOrFail();

        $examIds = $questionData['exam_ids']
            ?? Exam::where('is_active', true)
                ->pluck('id')
                ->all();

        $payload = [
            'question_text' =>
                $questionData['question_text'],

            'question_text_hi' =>
                $questionData['question_text_hi']
                ?? null,

            'explanation' =>
                $questionData['explanation']
                ?? $item->summary,

            'explanation_hi' =>
                $questionData['explanation_hi']
                ?? null,

            'subject_id' => $subject->id,
            'exam_ids' => $examIds,

            'difficulty' =>
                $questionData['difficulty']
                ?? 'medium',

            'language' =>
                $questionData['language']
                ?? 'bilingual',

            'is_current_affairs' => true,

            'current_affair_date' =>
                optional($item->published_at)
                    ?->toDateString(),

            'source_url' => $item->source_url,

            'generation_method' =>
                $questionData['generation_method']
                ?? 'automated',

            'options' =>
                $questionData['options'],
        ];

        $batch = $this->ingestion->ingest(
            [$payload],
            $item->source,
            'current_affairs'
        );

        if ($batch->accepted_count !== 1) {
            throw new RuntimeException(
                'Current affairs question was not accepted.'
            );
        }

        $item->update([
            'question_generated' => true,
            'status' => 'processed',
        ]);
    }
}
