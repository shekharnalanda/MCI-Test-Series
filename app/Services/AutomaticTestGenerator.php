<?php

namespace App\Services;

use App\Models\Exam;
use App\Models\Question;
use App\Models\Test;
use App\Models\TestSeries;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class AutomaticTestGenerator
{
    public function generate(
        Exam $exam,
        int $questionCount = 25,
        string $difficulty = 'mixed',
        string $type = 'practice'
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
            );

        if ($difficulty !== 'mixed') {
            $query->where('difficulty', $difficulty);
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

        $questions = $selectionQuery
            ->orderBy('usage_count')
            ->inRandomOrder()
            ->limit($questionCount)
            ->get();

        if ($questions->count() < $questionCount) {
            throw new RuntimeException(
                "Only {$questions->count()} eligible questions available; ".
                "{$questionCount} required."
            );
        }

        return DB::transaction(function () use (
            $exam,
            $questions,
            $questionCount,
            $difficulty,
            $type
        ) {
            $series = TestSeries::firstOrCreate(
                ['slug' => 'auto-'.$exam->slug],
                [
                    'exam_id' => $exam->id,
                    'name' => $exam->name.' Automatic Test Series',
                    'name_hi' =>
                        ($exam->name_hi ?: $exam->name).
                        ' ऑटो टेस्ट सीरीज',
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
                    ' Auto Practice Test '.$sequence,

                'title_hi' =>
                    ($exam->name_hi ?: $exam->name).
                    ' ऑटो प्रैक्टिस टेस्ट '.$sequence,

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
                    'selection' => 'least_used_randomized',
                    'verified_only' => true,
                    'published_only' => true,
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
}
