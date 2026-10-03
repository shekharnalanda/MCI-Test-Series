<?php

namespace App\Services;

use App\Models\Exam;
use App\Models\ExamCategory;
use App\Models\Question;
use App\Models\Subject;
use App\Models\Test;
use App\Models\Topic;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class TestCatalogService
{
    public function filters(Request $request): array
    {
        return $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'category' => ['nullable', 'integer', 'min:1'],
            'exam' => ['nullable', 'integer', 'min:1'],
            'subject' => ['nullable', 'integer', 'min:1'],
            'topic' => ['nullable', 'integer', 'min:1'],
            'type' => ['nullable', Rule::in(['demo', 'topic', 'practice', 'full_mock', 'previous_year', 'current_affairs', 'special'])],
        ]);
    }

    /**
     * The caller supplies the access/availability scope; every option uses that same scope.
     *
     * @param  array{q?: ?string, category?: mixed, exam?: mixed, subject?: mixed, topic?: mixed, type?: ?string}  $filters
     * @return array{tests: mixed, categories: Collection, exams: Collection, subjects: Collection, topics: Collection, testTypes: Collection, filters: array}
     */
    public function browse(Builder $available, array $filters): array
    {
        $availableExams = Exam::whereIn('id', (clone $available)->select('tests.exam_id'))
            ->orderBy('name')->get(['id', 'name', 'exam_category_id']);
        $categories = ExamCategory::whereIn('id', $availableExams->pluck('exam_category_id')->unique())
            ->orderBy('name')->get(['id', 'name']);

        if (empty($filters['category']) && ! empty($filters['exam'])) {
            $filters['category'] = $availableExams->firstWhere('id', (int) $filters['exam'])?->exam_category_id;
        }

        $exams = empty($filters['category']) ? collect() : $availableExams
            ->where('exam_category_id', (int) $filters['category'])->values();

        if (! empty($filters['category']) && ! empty($filters['exam']) && ! $exams->contains('id', (int) $filters['exam'])) {
            unset($filters['exam'], $filters['subject'], $filters['topic'], $filters['type']);
        }

        $scoped = (clone $available)
            ->when($filters['category'] ?? null, fn (Builder $query) => $query->whereIn('exam_id', $exams->pluck('id')))
            ->when($filters['exam'] ?? null, fn (Builder $query, $id) => $query->where('exam_id', $id));

        $testIds = empty($filters['exam']) ? collect() : (clone $scoped)->pluck('tests.id');
        $questions = Question::where('is_active', true)->where('is_published', true)
            ->whereIn('id', DB::table('question_test')->whereIn('test_id', $testIds)->select('question_id'));
        $subjects = empty($filters['exam']) ? collect() : Subject::whereIn('id',
            (clone $questions)->distinct()->pluck('subject_id')
                ->merge(Test::whereIn('id', $testIds)->distinct()->pluck('subject_id'))->filter()->unique()
        )->orderBy('name')->get(['id', 'name']);

        if (empty($filters['exam']) || ! $subjects->contains('id', (int) ($filters['subject'] ?? 0))) {
            unset($filters['subject'], $filters['topic']);
        }

        $topics = empty($filters['subject']) ? collect() : Topic::where('subject_id', $filters['subject'])
            ->where('is_active', true)
            ->whereIn('id', (clone $questions)->where('subject_id', $filters['subject'])->distinct()->pluck('topic_id')
                ->merge(Test::whereIn('id', $testIds)->distinct()->pluck('topic_id'))->filter()->unique())
            ->orderBy('name')->get(['id', 'name']);

        if (! $topics->contains('id', (int) ($filters['topic'] ?? 0))) {
            unset($filters['topic']);
        }

        $this->contentFilter($scoped, $filters);
        $testTypes = (clone $scoped)->whereNotNull('test_type')->distinct()->orderBy('test_type')->pluck('test_type');
        if (! empty($filters['type']) && ! $testTypes->contains($filters['type'])) {
            unset($filters['type']);
        }

        $tests = $scoped->with(['exam.category', 'subject', 'topic'])
            ->when($filters['type'] ?? null, fn (Builder $query, $type) => $query->where('test_type', $type))
            ->when($filters['q'] ?? null, fn (Builder $query, $text) => $query->where(function (Builder $nested) use ($text) {
                $nested->where('title', 'like', '%'.$text.'%')
                    ->orWhereHas('exam', fn (Builder $exam) => $exam->where('name', 'like', '%'.$text.'%'));
            }))->latest('id')->paginate(20)
            ->appends(array_filter($filters, fn (mixed $value): bool => $value !== null && $value !== ''));

        return compact('tests', 'categories', 'exams', 'subjects', 'topics', 'testTypes', 'filters');
    }

    private function availableQuestions(Builder $questions): Builder
    {
        return $questions->where('questions.is_active', true)->where('questions.is_published', true);
    }

    /** @param array{subject?: mixed, topic?: mixed} $filters */
    private function contentFilter(Builder $tests, array $filters): void
    {
        if (empty($filters['subject'])) {
            return;
        }

        if (! empty($filters['topic'])) {
            $tests->chapter((int) $filters['subject'], (int) $filters['topic']);

            return;
        }

        $tests->where(function (Builder $query) use ($filters) {
            $query->where(function (Builder $metadata) use ($filters) {
                $metadata->where('tests.subject_id', $filters['subject'])
                    ->when($filters['topic'] ?? null, fn (Builder $test, $id) => $test->where('tests.topic_id', $id));
            })->orWhereHas('questions', fn (Builder $questions) => $this->availableQuestions($questions)
                ->where('questions.subject_id', $filters['subject'])
                ->when($filters['topic'] ?? null, fn (Builder $question, $id) => $question->where('questions.topic_id', $id)));
        });
    }
}
