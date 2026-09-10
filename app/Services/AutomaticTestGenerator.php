<?php

namespace App\Services;

use App\Models\Exam;
use App\Models\Question;
use App\Models\Test;
use App\Models\TestSeries;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class AutomaticTestGenerator
{
    public function generate(
        Exam $exam,
        int $questionCount = 25,
        string $difficulty = 'mixed',
        string $type = 'practice',
        int $minPoolMultiple = 1
    ): Test {
        if ($questionCount < 1 || $questionCount > 500) {
            throw new RuntimeException(
                'Question count must be between 1 and 500.'
            );
        }

        $query = Question::query()
            ->where('is_active', true)
            ->where('is_published', true)
            ->where('verification_status', 'verified')
            ->whereHas(
                'exams',
                fn ($q) => $q->where('exams.id', $exam->id)
            )
            ->whereExists(function ($subjectMatch) use ($exam): void {
                $subjectMatch
                    ->selectRaw('1')
                    ->from('exam_subject')
                    ->whereColumn(
                        'exam_subject.subject_id',
                        'questions.subject_id'
                    )
                    ->where('exam_subject.exam_id', $exam->id);
            });

        if ($difficulty !== 'mixed') {
            $query->where('difficulty', $difficulty);
        }

        $eligibleCount = (clone $query)->count();
        $requiredPool = $questionCount * max(1, $minPoolMultiple);

        if ($eligibleCount < $requiredPool) {
            throw new RuntimeException(
                "Quality gate: only {$eligibleCount} eligible questions available; ".
                "{$requiredPool} required for a {$minPoolMultiple}x diversity pool."
            );
        }

        /*
         * Lowest usage_count first prevents excessive repetition.
         * inRandomOrder() is Laravel database-driver aware:
         * MySQL => RAND(), SQLite/PostgreSQL => appropriate equivalent.
         */
        $usedQuestionIds = DB::table('question_test')
            ->join('tests', 'tests.id', '=', 'question_test.test_id')
            ->where('tests.exam_id', $exam->id)
            ->pluck('question_test.question_id');

        $unusedQuery = clone $query;

        if ($usedQuestionIds->isNotEmpty()) {
            $unusedQuery->whereNotIn('questions.id', $usedQuestionIds);
        }

        $selectionQuery = $unusedQuery->count() >= $questionCount
            ? $unusedQuery
            : $query;

        $questions = $this->selectTopicBalancedQuestions(
            $selectionQuery,
            $questionCount
        );

        if ($questions->count() < $questionCount) {
            throw new RuntimeException(
                "Only {$questions->count()} eligible questions available; ".
                "{$questionCount} required."
            );
        }

        $typeLabels = [
            'practice' => ['Practice Test', 'प्रैक्टिस टेस्ट'],
            'full_mock' => ['Full Mock Test', 'फुल मॉक टेस्ट'],
            'topic' => ['Topic Test', 'टॉपिक टेस्ट'],
            'previous_year' => ['Previous Year Test', 'पिछले वर्ष का टेस्ट'],
            'special' => ['Special Test', 'विशेष टेस्ट'],
        ];

        [$typeLabel, $typeLabelHi] = $typeLabels[$type]
            ?? ['Test', 'टेस्ट'];

        return DB::transaction(function () use (
            $exam,
            $questions,
            $questionCount,
            $difficulty,
            $type,
            $typeLabel,
            $typeLabelHi,
            $eligibleCount,
            $minPoolMultiple
        ) {
            $series = TestSeries::firstOrCreate(
                ['slug' => 'auto-'.$type.'-'.$exam->slug],
                [
                    'exam_id' => $exam->id,
                    'name' => $exam->name.' Automatic '.$typeLabel.' Series',
                    'name_hi' =>
                        ($exam->name_hi ?: $exam->name).
                        ' ऑटो '.$typeLabelHi.' सीरीज',
                    'series_type' => $type,
                    'price' => 0,
                    'is_free' => false,
                    'is_active' => true,
                ]
            );

            $sequence = Test::where(
                'test_series_id',
                $series->id
            )->count() + 1;

            $test = Test::create([
                'test_series_id' => $series->id,
                'exam_id' => $exam->id,

                'title' =>
                    $exam->name.
                    ' Auto '.$typeLabel.' '.$sequence,

                'title_hi' =>
                    ($exam->name_hi ?: $exam->name).
                    ' ऑटो '.$typeLabelHi.' '.$sequence,

                'instructions' =>
                    'Automatically generated from verified MCI Question Bank.',

                'test_type' => $type,
                'total_questions' => $questionCount,
                'duration_minutes' => max(10, $questionCount),

                'positive_marks' => 1,
                'negative_marks' => 0.25,

                'randomize_questions' => true,
                'randomize_options' => true,

                'auto_generated' => true,
                'is_demo' => false,
                'is_active' => true,

                'generation_rules' => [
                    'difficulty' => $difficulty,
                    'question_count' => $questionCount,
                    'selection' => 'least_used_topic_balanced',
                    'verified_only' => true,
                    'published_only' => true,
                    'subject_alignment_required' => true,
                    'topic_balance_required' => true,
                    'eligible_pool_size' => $eligibleCount,
                    'minimum_pool_multiple' => $minPoolMultiple,
                    'generated_at' => now()->toIso8601String(),
                ],
            ]);

            $sync = [];

            foreach ($questions->values() as $index => $question) {
                $sync[$question->id] = [
                    'sort_order' => $index + 1,
                    'marks' => 1,
                    'negative_marks' => 0.25,
                ];
            }

            $test->questions()->sync($sync);

            Question::whereKey($questions->modelKeys())->increment("usage_count");

            return $test->fresh([
                'questions',
                'exam',
                'series',
            ]);
        });
    }

    /**
     * Keep the least-used rotation while distributing each test across as
     * many available topics as possible. The larger candidate window avoids
     * a single high-volume topic crowding out the rest of an exam syllabus.
     */
    private function selectTopicBalancedQuestions(
        Builder $query,
        int $questionCount
    ): Collection {
        $candidateLimit = max($questionCount, $questionCount * 4);

        $buckets = (clone $query)
            ->orderBy('usage_count')
            ->inRandomOrder()
            ->limit($candidateLimit)
            ->get()
            ->groupBy(fn (Question $question): string =>
                (string) ($question->topic_id ?? 'unclassified')
            )
            ->map(fn (Collection $questions): Collection =>
                $questions->values()
            );

        $selected = collect();

        while ($selected->count() < $questionCount && $buckets->isNotEmpty()) {
            foreach ($buckets->keys() as $key) {
                if ($selected->count() >= $questionCount) {
                    break;
                }

                $question = $buckets->get($key)?->shift();

                if ($question) {
                    $selected->push($question);
                }

                if ($buckets->get($key)?->isEmpty()) {
                    $buckets->forget($key);
                }
            }
        }

        return $selected;
    }
}
