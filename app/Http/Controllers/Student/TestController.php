<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Test;
use App\Models\TestAttempt;
use App\Models\Exam;
use App\Models\ExamCategory;
use App\Services\ExamEngineService;
use App\Services\StudentTestAccessService;
use Illuminate\Http\Request;

class TestController extends Controller
{
    public function index(Request $request, StudentTestAccessService $access)
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'category' => ['nullable', 'integer'],
            'exam' => ['nullable', 'integer'],
            'type' => ['nullable', 'string', 'max:50'],
        ]);
        $demoAccess = (bool) $request->session()->get('demo_access', false);
        $student = $request->user()->studentProfile;
        $enrollment = $demoAccess ? null : ($student ? $access->activeEnrollment($student) : null);
        $selectedIds = $enrollment ? ($access->selectedTestIds($enrollment) ?? []) : [];
        $attemptedIds = $enrollment ? $access->attemptedIds($enrollment) : [];
        $completedIds = $enrollment ? $access->completedIds($enrollment) : [];

        $applyAccess = function ($query) use ($demoAccess, $enrollment) {
            if ($demoAccess) return $query->where('is_demo', true);
            if (! $enrollment) return $query->whereRaw('1 = 0');
            if ($enrollment->package_exam_id) $query->where('exam_id', $enrollment->package_exam_id);
            return $query;
        };

        $tests = Test::with('exam.category')
            ->where('is_active', true)
            ->tap($applyAccess)
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

        $testScope = function ($query) use ($demoAccess, $enrollment) {
            $query->where('is_active', true);
            if ($demoAccess) return $query->where('is_demo', true);
            if (! $enrollment) return $query->whereRaw('1 = 0');
            if ($enrollment->package_exam_id) $query->where('exam_id', $enrollment->package_exam_id);
            return $query;
        };
        $categories = ExamCategory::whereHas('exams.tests', $testScope)->orderBy('name')->get(['id', 'name']);
        $exams = Exam::whereHas('tests', $testScope)
            ->when($filters['category'] ?? null, fn ($query, $category) => $query->where('exam_category_id', $category))
            ->orderBy('name')->get(['id', 'name', 'exam_category_id']);
        $testTypes = Test::where('is_active', true)->tap($applyAccess)
            ->whereNotNull('test_type')->distinct()->orderBy('test_type')->pluck('test_type');

        return view('student.tests.index', compact('tests', 'categories', 'exams', 'testTypes', 'filters', 'demoAccess', 'enrollment', 'selectedIds', 'attemptedIds', 'completedIds'));
    }

    public function select(Test $test, StudentTestAccessService $access)
    {
        $student = auth()->user()->studentProfile;
        $enrollment = $student ? $access->activeEnrollment($student) : null;
        abort_unless($enrollment, 403, 'No active test package is assigned.');
        try { $access->select($enrollment, $test); } catch (\RuntimeException $e) { return back()->withErrors(['package' => $e->getMessage()]); }
        return back()->with('success', 'Test added to your personal test pack.');
    }

    public function unselect(Test $test, StudentTestAccessService $access)
    {
        $student = auth()->user()->studentProfile;
        $enrollment = $student ? $access->activeEnrollment($student) : null;
        abort_unless($enrollment, 403);
        try { $access->unselect($enrollment, $test); } catch (\RuntimeException $e) { return back()->withErrors(['package' => $e->getMessage()]); }
        return back()->with('success', 'Test removed from your pack.');
    }

    public function start(Test $test, ExamEngineService $engine, StudentTestAccessService $access)
    {
        if ((bool) request()->session()->get('demo_access', false)) {
            abort_unless($test->is_active && $test->is_demo, 403, 'This test is not included in the free demo.');
        }

        $student = auth()->user()->studentProfile;
        abort_unless($student && $student->status === 'active', 403);
        if (! (bool) request()->session()->get('demo_access', false)) {
            $enrollment = $access->activeEnrollment($student);
            abort_unless($enrollment, 403, 'No active test package is assigned.');
            abort_unless($access->allows($enrollment, $test), 403, 'This test is not included in your package.');
            abort_if(in_array((int) $test->id, $access->completedIds($enrollment), true), 403, 'This subscribed test has already been completed.');
        }
        $attempt = $engine->start($test, $student);
        if (isset($enrollment)) $access->syncUsage($enrollment);

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
        $attempt->load(['test.exam', 'attemptQuestions.question.options', 'answers.selectedOption']);
        $canReviewAnswers = $attempt->test->answer_visibility === 'immediate'
            || ($attempt->test->answer_visibility === 'scheduled'
                && $attempt->test->answers_available_at
                && now()->greaterThanOrEqualTo($attempt->test->answers_available_at));
        return view('student.tests.result', compact('attempt', 'canReviewAnswers'));
    }

    private function authorizeAttempt(TestAttempt $attempt): void
    {
        $student = auth()->user()->studentProfile;
        abort_unless($student && $attempt->student_profile_id === $student->id, 403);
    }
}
