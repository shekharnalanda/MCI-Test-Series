<?php

namespace App\Services;

use App\Models\Exam;
use App\Models\ExamCategory;
use App\Models\Subject;
use App\Models\Topic;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
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
        $categories = ExamCategory::whereIn('id', Exam::whereIn('id', (clone $available)->select('tests.exam_id'))->select('exam_category_id'))
            ->orderBy('name')->get(['id', 'name']);

        if (empty($filters['category']) && ! empty($filters['exam'])) {
            $filters['category'] = Exam::whereIn('id', (clone $available)->select('tests.exam_id'))
                ->whereKey($filters['exam'])->value('exam_category_id');
        }

        $exams = empty($filters['category']) ? collect() : Exam::where('exam_category_id', $filters['category'])
            ->whereIn('id', (clone $available)->select('tests.exam_id'))->orderBy('name')->get(['id', 'name']);

        if (! empty($filters['category']) && ! empty($filters['exam']) && ! $exams->contains('id', (int) $filters['exam'])) {
            unset($filters['exam'], $filters['subject'], $filters['topic'], $filters['type']);
        }

        $scoped = (clone $available)
            ->when($filters['category'] ?? null, fn (Builder $query, $id) => $query->whereHas('exam', fn (Builder $exam) => $exam->where('exam_category_id', $id)))
            ->when($filters['exam'] ?? null, fn (Builder $query, $id) => $query->where('exam_id', $id));

        $subjects = empty($filters['exam']) ? collect() : Subject::where(function (Builder $query) use ($scoped) {
            $query->whereIn('id', (clone $scoped)->select('tests.subject_id'))
                ->orWhereHas('questions', fn (Builder $questions) => $this->availableQuestions($questions)
                    ->whereHas('tests', fn (Builder $tests) => $tests->whereIn('tests.id', (clone $scoped)->select('tests.id'))));
        })->orderBy('name')->get(['id', 'name']);

        if (empty($filters['exam']) || ! $subjects->contains('id', (int) ($filters['subject'] ?? 0))) {
            unset($filters['subject'], $filters['topic']);
        }

        $topics = empty($filters['subject']) ? collect() : Topic::where('subject_id', $filters['subject'])
            ->where(function (Builder $query) use ($scoped, $filters) {
                $query->whereIn('id', (clone $scoped)->select('tests.topic_id'))
                    ->orWhereHas('questions', fn (Builder $questions) => $this->availableQuestions($questions)
                        ->where('questions.subject_id', $filters['subject'])
                        ->whereHas('tests', fn (Builder $tests) => $tests->whereIn('tests.id', (clone $scoped)->select('tests.id'))));
            })->orderBy('name')->get(['id', 'name']);

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
