<?php

namespace App\Services;

use App\Models\QuestionGenerationJob;
use Illuminate\Support\Collection;

class QuestionGenerationBriefService
{
    public function pending(
        string $difficulty = 'hard',
        int $limit = 25
    ): Collection {
        return QuestionGenerationJob::query()
            ->whereIn('status', ['pending', 'partial'])
            ->with([
                'exam:id,name,name_hi,slug,official_url',
                'subject:id,name,name_hi,slug',
                'subject.topics' => fn ($query) => $query
                    ->where('is_active', true)
                    ->orderBy('sort_order')
                    ->orderBy('id'),
            ])
            ->orderByDesc('priority')
            ->orderBy('id')
            ->get()
            ->map(function (QuestionGenerationJob $job) use ($difficulty) {
                $rules = $job->generation_rules ?? [];
                $deficits = $rules['difficulty_deficits'] ?? [];
                $required = $difficulty === 'all'
                    ? array_sum(array_map('intval', $deficits))
                    : (int) ($deficits[$difficulty] ?? 0);

                if ($required < 1 || ! $job->exam || ! $job->subject) {
                    return null;
                }

                return [
                    'job_code' => $job->job_code,
                    'priority' => (int) $job->priority,
                    'status' => $job->status,
                    'exam' => [
                        'id' => $job->exam->id,
                        'name' => $job->exam->name,
                        'name_hi' => $job->exam->name_hi,
                        'slug' => $job->exam->slug,
                        'official_url' => $job->exam->official_url,
                    ],
                    'subject' => [
                        'id' => $job->subject->id,
                        'name' => $job->subject->name,
                        'name_hi' => $job->subject->name_hi,
                        'slug' => $job->subject->slug,
                    ],
                    'difficulty' => $difficulty,
                    'required_count' => $required,
                    'difficulty_deficits' => [
                        'easy' => (int) ($deficits['easy'] ?? 0),
                        'medium' => (int) ($deficits['medium'] ?? 0),
                        'hard' => (int) ($deficits['hard'] ?? 0),
                    ],
                    'eligible_topics' => $job->subject->topics->map(fn ($topic) => [
                        'id' => $topic->id,
                        'name' => $topic->name,
                        'name_hi' => $topic->name_hi,
                        'slug' => $topic->slug,
                    ])->values()->all(),
                    'content_requirements' => [
                        'language' => 'bilingual',
                        'trusted_official_or_open_source' => true,
                        'https_source_url' => true,
                        'source_reference' => true,
                        'source_published_at' => true,
                        'bilingual_explanation' => true,
                        'four_bilingual_distinct_options' => true,
                        'exactly_one_correct_option' => true,
                        'duplicate_and_ambiguity_checks' => true,
                    ],
                ];
            })
            ->filter()
            ->take($limit)
            ->values();
    }
}
