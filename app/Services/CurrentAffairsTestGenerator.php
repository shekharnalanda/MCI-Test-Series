<?php

namespace App\Services;

use App\Models\Exam;
use App\Models\Question;
use App\Models\Test;
use App\Models\TestSeries;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class CurrentAffairsTestGenerator
{
    public function generate(
        string $period = 'weekly',
        int $questionCount = 10
    ): Test {
        $days = match ($period) {
            'daily' => 1,
            'weekly' => 7,
            'monthly' => 30,
            default => throw new RuntimeException(
                'Invalid current affairs test period.'
            ),
        };

        $query = Question::query()
            ->where('is_current_affairs', true)
            ->where('is_active', true)
            ->where('is_published', true)
            ->where(
                'verification_status',
                'verified'
            )
            ->where(
                'current_affair_date',
                '>=',
                now()->subDays($days)->toDateString()
            );

        $usedQuestionIds = DB::table('question_test')
            ->join('tests', 'tests.id', '=', 'question_test.test_id')
            ->join(
                'test_series',
                'test_series.id',
                '=',
                'tests.test_series_id'
            )
            ->where(
                'test_series.slug',
                'current-affairs-'.$period
            )
            ->pluck('question_test.question_id');

        if ($usedQuestionIds->isNotEmpty()) {
            $unusedCount = (clone $query)
                ->whereNotIn(
                    'questions.id',
                    $usedQuestionIds
                )
                ->count();

            if ($unusedCount >= $questionCount) {
                $query->whereNotIn(
                    'questions.id',
                    $usedQuestionIds
                );
            }
        }

        $questions = $query
            ->orderByDesc('freshness_score')
            ->orderBy('usage_count')
            ->limit($questionCount)
            ->get();

        if ($questions->count() < $questionCount) {
            throw new RuntimeException(
                "Only {$questions->count()} current affairs questions ".
                "available; {$questionCount} required."
            );
        }

        return DB::transaction(
            function () use (
                $period,
                $questionCount,
                $questions
            ) {
                $series = TestSeries::firstOrCreate(
                    [
                        'slug' =>
                            'current-affairs-'.$period
                    ],
                    [
                        'name' =>
                            ucfirst($period).
                            ' Current Affairs Test Series',

                        'name_hi' =>
                            ucfirst($period).
                            ' करेंट अफेयर्स टेस्ट सीरीज',

                        'series_type' =>
                            'current_affairs',

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

                    'title' =>
                        ucfirst($period).
                        ' Current Affairs Test '.
                        $sequence,

                    'title_hi' =>
                        'करेंट अफेयर्स टेस्ट '.
                        $sequence,

                    'test_type' =>
                        'current_affairs',

                    'total_questions' =>
                        $questionCount,

                    'duration_minutes' =>
                        max(10, $questionCount),

                    'positive_marks' => 1,
                    'negative_marks' => 0.25,

                    'randomize_questions' => true,
                    'randomize_options' => true,

                    'auto_generated' => true,
                    'is_demo' => false,
                    'is_active' => true,

                    'generation_rules' => [
                        'period' => $period,
                        'question_count' =>
                            $questionCount,
                        'freshness_priority' => true,
                    ],
                ]);

                $sync = [];

                foreach (
                    $questions->values()
                    as $index => $question
                ) {
                    $sync[$question->id] = [
                        'sort_order' => $index + 1,
                        'marks' => 1,
                        'negative_marks' => 0.25,
                    ];
                }

                $test->questions()->sync($sync);

            Question::whereKey($questions->modelKeys())
                ->increment('usage_count');

                return $test->fresh('questions');
            }
        );
    }
}
