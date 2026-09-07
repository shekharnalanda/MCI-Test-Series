<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Test;
use App\Models\TestAttempt;
use App\Models\Exam;
use App\Models\ExamCategory;
use App\Services\ExamEngineService;
use Illuminate\Http\Request;

class TestController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'category' => ['nullable', 'integer'],
            'exam' => ['nullable', 'integer'],
            'type' => ['nullable', 'string', 'max:50'],
        ]);
        $demoAccess = $request->session()->boolean('demo_access');

        $tests = Test::with('exam.category')
            ->where('is_active', true)
            ->when($demoAccess, fn ($query) => $query->where('is_demo', true))
            ->when($filters['category'] ?? null, fn ($query, $category) =>
                $query->whereHas('exam', fn ($exam) => $exam->where('exam_category_id', $category)))
            ->when($filters['exam'] ?? null, fn ($query, $exam) => $query->where('exam_id', $exam))
            ->when($filters['type'] ?? null, fn ($query, $type) => $query->where('test_type', $type))
            ->when($filters['q'] ?? null, function ($query, $search) {
                $query->where(function ($nested) use ($search) {
                    $nested->where('title', 'like', '%'.$search.'%')
                        ->orWhereHas('exam', fn ($exam) => $exam->where('name', 'like', '%'.$search.'%'));
                });
            })
            ->latest()
            ->paginate(20)
            ->withQueryString();

        $testScope = fn ($query) => $query->where('is_active', true)
            ->when($demoAccess, fn ($tests) => $tests->where('is_demo', true));
        $categories = ExamCategory::whereHas('exams.tests', $testScope)->orderBy('name')->get(['id', 'name']);
        $exams = Exam::whereHas('tests', $testScope)
            ->when($filters['category'] ?? null, fn ($query, $category) => $query->where('exam_category_id', $category))
            ->orderBy('name')->get(['id', 'name', 'exam_category_id']);
        $testTypes = Test::where('is_active', true)->when($demoAccess, fn ($query) => $query->where('is_demo', true))
            ->whereNotNull('test_type')->distinct()->orderBy('test_type')->pluck('test_type');

        return view('student.tests.index', compact('tests', 'categories', 'exams', 'testTypes', 'filters', 'demoAccess'));
    }

    public function start(Test $test, ExamEngineService $engine)
    {
        if (request()->session()->boolean('demo_access')) {
            abort_unless($test->is_active && $test->is_demo, 403, 'This test is not included in the free demo.');
        }

        $student = auth()->user()->studentProfile;
        abort_unless($student && $student->status === 'active', 403);
        $attempt = $engine->start($test, $student);

        return redirect()->route('student.attempts.show', $attempt);
    }

    public function show(TestAttempt $attempt, ExamEngineService $engine)
    {
        $this->authorizeAttempt($attempt);
        if ($attempt->status === 'evaluated') return redirect()->route('student.attempts.result', $attempt);
        if ($engine->isExpired($attempt)) { $engine->submit($attempt); return redirect()->route('student.attempts.result', $attempt); }
        $attempt->load(['test', 'attemptQuestions.question.options', 'answers']);
        $deadline = $attempt->started_at->copy()->addMinutes($attempt->test->duration_minutes);
        return view('student.tests.exam', compact('attempt', 'deadline'));
    }

    public function answer(Request $request, TestAttempt $attempt, ExamEngineService $engine)
    {
        $this->authorizeAttempt($attempt);
        if ($engine->isExpired($attempt)) { $engine->submit($attempt); return response()->json(['expired' => true], 409); }
        $validated = $request->validate(['question_id' => ['required','integer'], 'selected_option_id' => ['nullable','integer'], 'marked_for_review' => ['nullable','boolean']]);
        $engine->saveAnswer($attempt, (int) $validated['question_id'], $validated['selected_option_id'] ?? null, (bool) ($validated['marked_for_review'] ?? false));
        return response()->json(['saved' => true]);
    }

    public function submit(TestAttempt $attempt, ExamEngineService $engine)
    {
        $this->authorizeAttempt($attempt);
        $engine->submit($attempt);
        return redirect()->route('student.attempts.result', $attempt);
    }

    public function result(TestAttempt $attempt)
    {
        $this->authorizeAttempt($attempt);
        abort_unless($attempt->status === 'evaluated', 404);
        $attempt->load(['test.exam', 'answers.question']);
        return view('student.tests.result', compact('attempt'));
    }

    private function authorizeAttempt(TestAttempt $attempt): void
    {
        $student = auth()->user()->studentProfile;
        abort_unless($student && $attempt->student_profile_id === $student->id, 403);
    }
}
