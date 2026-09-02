<?php

namespace App\Services;

use App\Models\AttemptAnswer;
use App\Models\AttemptQuestion;
use App\Models\StudentProfile;
use App\Models\Test;
use App\Models\TestAttempt;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ExamEngineService
{
    public function start(Test $test, StudentProfile $student): TestAttempt
    {
        if (!$test->is_active) {
            throw new RuntimeException('This test is not active.');
        }

        $existing = TestAttempt::where('student_profile_id', $student->id)
            ->where('test_id', $test->id)
            ->where('status', 'started')
            ->latest('id')
            ->first();

        if ($existing) {
            return $existing;
        }

        return DB::transaction(function () use ($test, $student) {

            $query = $test->questions()
                ->where('questions.is_active', true)
                ->where('questions.is_published', true);

            $questions = $test->randomize_questions
                ? $query->inRandomOrder()->get()
                : $query->orderByPivot('sort_order')->get();

            if ($questions->isEmpty()) {
                throw new RuntimeException(
                    'No published questions are available for this test.'
                );
            }

            $attemptNo = TestAttempt::where(
                'student_profile_id',
                $student->id
            )->where(
                'test_id',
                $test->id
            )->max('attempt_number');

            $attempt = TestAttempt::create([
                'student_profile_id' => $student->id,
                'test_id' => $test->id,
                'attempt_number' => ((int) $attemptNo) + 1,
                'started_at' => now(),
                'total_questions' => $questions->count(),
                'maximum_marks' => $questions->sum(
                    fn ($q) => $q->pivot->marks ?? $test->positive_marks
                ),
                'status' => 'started',
            ]);

            foreach ($questions->values() as $index => $question) {

                $optionIds = $question->options()
                    ->orderBy('sort_order')
                    ->pluck('id')
                    ->all();

                if ($test->randomize_options) {
                    shuffle($optionIds);
                }

                AttemptQuestion::create([
                    'test_attempt_id' => $attempt->id,
                    'question_id' => $question->id,
                    'question_order' => $index + 1,
                    'option_order' => $optionIds,
                    'marks' => $question->pivot->marks
                        ?? $test->positive_marks,
                    'negative_marks' => $question->pivot->negative_marks
                        ?? $test->negative_marks,
                ]);

                $question->increment('usage_count');
                $question->update(['last_used_at' => now()]);
            }

            return $attempt;
        });
    }

    public function saveAnswer(
        TestAttempt $attempt,
        int $questionId,
        ?int $selectedOptionId,
        bool $markedForReview = false
    ): AttemptAnswer {

        $this->ensureActive($attempt);

        $allowed = $attempt->attemptQuestions()
            ->where('question_id', $questionId)
            ->exists();

        if (!$allowed) {
            throw new RuntimeException(
                'Question does not belong to this attempt.'
            );
        }

        if ($selectedOptionId !== null) {
            $validOption = \App\Models\QuestionOption::where(
                'id',
                $selectedOptionId
            )->where(
                'question_id',
                $questionId
            )->exists();

            if (!$validOption) {
                throw new RuntimeException(
                    'Selected option does not belong to this question.'
                );
            }
        }

        return AttemptAnswer::updateOrCreate(
            [
                'test_attempt_id' => $attempt->id,
                'question_id' => $questionId,
            ],
            [
                'selected_option_id' => $selectedOptionId,
                'is_marked_for_review' => $markedForReview,
            ]
        );
    }

    public function submit(TestAttempt $attempt): TestAttempt
    {
        if ($attempt->status === 'evaluated') {
            return $attempt->fresh();
        }

        $this->ensureActive($attempt);

        return DB::transaction(function () use ($attempt) {

            $attempt->load([
                'attemptQuestions.question.options',
                'answers',
            ]);

            $answers = $attempt->answers->keyBy('question_id');

            $correct = 0;
            $wrong = 0;
            $attempted = 0;
            $score = 0.0;

            foreach ($attempt->attemptQuestions as $snapshot) {

                $answer = $answers->get($snapshot->question_id);

                if (!$answer || !$answer->selected_option_id) {
                    continue;
                }

                $attempted++;

                $selected = $snapshot->question->options
                    ->firstWhere('id', $answer->selected_option_id);

                $isCorrect = (bool) ($selected?->is_correct);

                $marks = $isCorrect
                    ? (float) $snapshot->marks
                    : -1 * (float) $snapshot->negative_marks;

                if ($isCorrect) {
                    $correct++;
                } else {
                    $wrong++;
                }

                $score += $marks;

                $answer->update([
                    'is_correct' => $isCorrect,
                    'marks_awarded' => $marks,
                ]);
            }

            $total = $attempt->total_questions;
            $unanswered = max(0, $total - $attempted);
            $maximum = (float) $attempt->maximum_marks;

            $percentage = $maximum > 0
                ? round(($score / $maximum) * 100, 2)
                : 0;

            $accuracy = $attempted > 0
                ? round(($correct / $attempted) * 100, 2)
                : 0;

            $attempt->update([
                'submitted_at' => now(),
                'time_taken_seconds' => $attempt->started_at
                    ? max(0, $attempt->started_at->diffInSeconds(now()))
                    : 0,
                'attempted_questions' => $attempted,
                'correct_answers' => $correct,
                'wrong_answers' => $wrong,
                'unanswered' => $unanswered,
                'obtained_marks' => $score,
                'percentage' => $percentage,
                'analytics' => [
                    'accuracy' => $accuracy,
                    'attempt_rate' => $total > 0
                        ? round(($attempted / $total) * 100, 2)
                        : 0,
                ],
                'status' => 'evaluated',
            ]);

            return $attempt->fresh();
        });
    }

    public function isExpired(TestAttempt $attempt): bool
    {
        if (!$attempt->started_at) {
            return false;
        }

        return now()->greaterThanOrEqualTo(
            $attempt->started_at
                ->copy()
                ->addMinutes($attempt->test->duration_minutes)
        );
    }

    private function ensureActive(TestAttempt $attempt): void
    {
        if ($attempt->status !== 'started') {
            throw new RuntimeException(
                'This test attempt is no longer active.'
            );
        }
    }
}
