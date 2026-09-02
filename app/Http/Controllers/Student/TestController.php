<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Test;
use App\Models\TestAttempt;
use App\Services\ExamEngineService;
use Illuminate\Http\Request;

class TestController extends Controller
{
    public function index()
    {
        $tests = Test::with('exam')
            ->where('is_active', true)
            ->latest()
            ->paginate(20);

        return view('student.tests.index', compact('tests'));
    }

    public function start(
        Test $test,
        ExamEngineService $engine
    ) {
        $student = auth()->user()->studentProfile;

        abort_unless($student && $student->status === 'active', 403);

        $attempt = $engine->start($test, $student);

        return redirect()->route(
            'student.attempts.show',
            $attempt
        );
    }

    public function show(
        TestAttempt $attempt,
        ExamEngineService $engine
    ) {
        $this->authorizeAttempt($attempt);

        if ($attempt->status === 'evaluated') {
            return redirect()->route(
                'student.attempts.result',
                $attempt
            );
        }

        if ($engine->isExpired($attempt)) {
            $engine->submit($attempt);

            return redirect()->route(
                'student.attempts.result',
                $attempt
            );
        }

        $attempt->load([
            'test',
            'attemptQuestions.question.options',
            'answers',
        ]);

        $deadline = $attempt->started_at
            ->copy()
            ->addMinutes($attempt->test->duration_minutes);

        return view(
            'student.tests.exam',
            compact('attempt', 'deadline')
        );
    }

    public function answer(
        Request $request,
        TestAttempt $attempt,
        ExamEngineService $engine
    ) {
        $this->authorizeAttempt($attempt);

        if ($engine->isExpired($attempt)) {
            $engine->submit($attempt);

            return response()->json([
                'expired' => true,
            ], 409);
        }

        $validated = $request->validate([
            'question_id' => ['required', 'integer'],
            'selected_option_id' => ['nullable', 'integer'],
            'marked_for_review' => ['nullable', 'boolean'],
        ]);

        $engine->saveAnswer(
            $attempt,
            (int) $validated['question_id'],
            isset($validated['selected_option_id'])
                ? (int) $validated['selected_option_id']
                : null,
            (bool) ($validated['marked_for_review'] ?? false)
        );

        return response()->json([
            'saved' => true,
        ]);
    }

    public function submit(
        TestAttempt $attempt,
        ExamEngineService $engine
    ) {
        $this->authorizeAttempt($attempt);

        $engine->submit($attempt);

        return redirect()->route(
            'student.attempts.result',
            $attempt
        );
    }

    public function result(TestAttempt $attempt)
    {
        $this->authorizeAttempt($attempt);

        abort_unless($attempt->status === 'evaluated', 404);

        $attempt->load([
            'test.exam',
            'answers.question',
        ]);

        return view(
            'student.tests.result',
            compact('attempt')
        );
    }

    private function authorizeAttempt(TestAttempt $attempt): void
    {
        $student = auth()->user()->studentProfile;

        abort_unless(
            $student &&
            $attempt->student_profile_id === $student->id,
            403
        );
    }
}
