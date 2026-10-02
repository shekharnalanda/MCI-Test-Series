<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Test;
use App\Models\TestAttempt;
use App\Services\ExamEngineService;
use App\Services\StudentTestAccessService;
use App\Services\TestCatalogService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TestController extends Controller
{
    public function index(Request $request, StudentTestAccessService $access, TestCatalogService $catalog): View
    {
        $filters = $catalog->filters($request);
        $demoAccess = (bool) $request->session()->get('demo_access', false);
        $student = $request->user()->studentProfile;
        $enrollment = $demoAccess ? null : ($student ? $access->activeEnrollment($student) : null);
        $selectedIds = $enrollment ? ($access->selectedTestIds($enrollment) ?? []) : [];
        $attemptedIds = $enrollment ? $access->attemptedIds($enrollment) : [];
        $completedIds = $enrollment ? $access->completedIds($enrollment) : [];

        $available = Test::where('is_active', true);
        if ($demoAccess) {
            $available->where('is_demo', true);
        } elseif (! $enrollment) {
            $available->whereRaw('1 = 0');
        } elseif ($enrollment->package_exam_id) {
            $available->where('exam_id', $enrollment->package_exam_id);
        }

        return view('student.tests.index', array_merge(
            $catalog->browse($available, $filters),
            compact('demoAccess', 'enrollment', 'selectedIds', 'attemptedIds', 'completedIds')
        ));
    }

    public function select(Test $test, StudentTestAccessService $access)
    {
        $student = auth()->user()->studentProfile;
        $enrollment = $student ? $access->activeEnrollment($student) : null;
        abort_unless($enrollment, 403, 'No active test package is assigned.');
        try {
            $access->select($enrollment, $test);
        } catch (\RuntimeException $e) {
            return back()->withErrors(['package' => $e->getMessage()]);
        }

        return back()->with('success', 'Test added to your personal test pack.');
    }

    public function unselect(Test $test, StudentTestAccessService $access)
    {
        $student = auth()->user()->studentProfile;
        $enrollment = $student ? $access->activeEnrollment($student) : null;
        abort_unless($enrollment, 403);
        try {
            $access->unselect($enrollment, $test);
        } catch (\RuntimeException $e) {
            return back()->withErrors(['package' => $e->getMessage()]);
        }

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
        if (isset($enrollment)) {
            $access->syncUsage($enrollment);
        }

        return redirect()->route('student.attempts.show', $attempt);
    }

    public function show(TestAttempt $attempt, ExamEngineService $engine)
    {
        $this->authorizeAttempt($attempt);
        if ($attempt->status === 'evaluated') {
            return redirect()->route('student.attempts.result', $attempt);
        }
        if ($engine->isExpired($attempt)) {
            $engine->submit($attempt);

            return redirect()->route('student.attempts.result', $attempt);
        }
        $attempt->load(['test', 'attemptQuestions.question.options', 'answers']);
        $deadline = $attempt->started_at->copy()->addMinutes($attempt->test->duration_minutes);

        return view('student.tests.exam', compact('attempt', 'deadline'));
    }

    public function answer(Request $request, TestAttempt $attempt, ExamEngineService $engine)
    {
        $this->authorizeAttempt($attempt);
        if ($engine->isExpired($attempt)) {
            $engine->submit($attempt);

            return response()->json(['expired' => true], 409);
        }
        $validated = $request->validate(['question_id' => ['required', 'integer'], 'selected_option_id' => ['nullable', 'integer'], 'marked_for_review' => ['nullable', 'boolean']]);
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
