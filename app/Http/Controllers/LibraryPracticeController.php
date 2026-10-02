<?php

namespace App\Http\Controllers;

use App\Models\Exam;
use App\Models\ExamCategory;
use App\Models\Test;
use App\Models\TestAttempt;
use App\Services\ExamEngineService;
use App\Services\LibraryPracticeBridge;
use App\Services\LibraryPracticeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class LibraryPracticeController extends Controller
{
    public function enter(Request $request, LibraryPracticeBridge $bridge, LibraryPracticeService $service): RedirectResponse
    {
        $data = $request->validate(['ticket' => ['required', 'string', 'size:64']]);
        try {
            $identity = $bridge->consume($data['ticket']);
            $practiceToken = $request->session()->get('library_practice_session_token') ?: bin2hex(random_bytes(32));
            $bridge->claimPractice($identity, $identity->device_hash, $practiceToken);
            $account = $service->linkIdentity($identity, $bridge);
            $request->session()->put('library_practice_device_hash', $identity->device_hash);
            $request->session()->put('library_practice_session_token', $practiceToken);
        } catch (\RuntimeException $e) {
            abort(403, 'Practice login expired or membership is inactive. Open it again from your library panel.');
        }
        $request->session()->regenerate();
        $request->session()->put('library_practice_account_id', $account->id);

        return redirect()->route('library-practice.index')->header('Cache-Control', 'no-store')->header('Referrer-Policy', 'no-referrer');
    }

    private function account(Request $request): object
    {
        return $request->attributes->get('library_practice_account');
    }

    public function index(Request $request, LibraryPracticeService $service, LibraryPracticeBridge $bridge): View
    {
        $account = $this->account($request);
        $month = $service->month($account);
        $filters = $request->validate(['category' => ['nullable', 'integer'], 'exam' => ['nullable', 'integer'], 'q' => ['nullable', 'string', 'max:100']]);
        $eligible = true;
        try {
            $service->assertMembership($account, $bridge);
        } catch (\RuntimeException $e) {
            $eligible = false;
        }
        $available = fn ($query) => $query->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('available_from')->orWhere('available_from', '<=', now()))
            ->where(fn ($q) => $q->whereNull('available_until')->orWhere('available_until', '>=', now()))
            ->whereHas('questions', fn ($q) => $q->where('questions.is_active', true)->where('questions.is_published', true));
        $tests = Test::with('exam.category')->tap($available)
            ->when($filters['category'] ?? null, fn ($q, $id) => $q->whereHas('exam', fn ($e) => $e->where('exam_category_id', $id)))
            ->when($filters['exam'] ?? null, fn ($q, $id) => $q->where('exam_id', $id))
            ->when($filters['q'] ?? null, fn ($q, $text) => $q->where('title', 'like', '%'.$text.'%'))
            ->latest('id')->paginate(20)->withQueryString();
        $examIds = Test::query()->tap($available)->whereNotNull('exam_id')->distinct()->pluck('exam_id');
        $exams = Exam::whereIn('id', $examIds)->orderBy('name')->get();
        $categories = ExamCategory::whereIn('id', $exams->pluck('exam_category_id')->filter()->unique())->orderBy('name')->get();
        $selections = DB::table('library_practice_selections')->where('library_practice_month_id', $month->id)->get()->keyBy('test_id');
        $selectedTests = Test::with('exam')->whereIn('id', $selections->keys())->get();
        $attempts = TestAttempt::with('test')->where('student_profile_id', $account->student_profile_id)
            ->whereIn('id', DB::table('library_practice_selections')->whereNotNull('test_attempt_id')->select('test_attempt_id'))
            ->latest('id')->paginate(10, ['*'], 'results_page');
        $completed = TestAttempt::whereIn('id', $selections->pluck('test_attempt_id')->filter())->where('status', 'evaluated')->count();

        return view('library-practice.index', compact('account', 'month', 'filters', 'eligible', 'tests', 'categories', 'exams', 'selections', 'selectedTests', 'attempts', 'completed'));
    }

    public function select(Request $request, Test $test, LibraryPracticeService $service, LibraryPracticeBridge $bridge): RedirectResponse
    {
        try {
            $account = $this->account($request);
            $service->assertMembership($account, $bridge);
            $service->select($service->month($account), $test);
        } catch (\RuntimeException $e) {
            return back()->withErrors(['practice' => $e->getMessage()]);
        }

        return back()->with('success', 'टेस्ट आपके मुफ्त मासिक सेट में जोड़ा गया।');
    }

    public function unselect(Request $request, Test $test, LibraryPracticeService $service): RedirectResponse
    {
        try {
            $service->unselect($service->month($this->account($request)), $test);
        } catch (\RuntimeException $e) {
            return back()->withErrors(['practice' => $e->getMessage()]);
        }

        return back()->with('success', 'सेट हट गया। अब दूसरा टेस्ट चुन सकते हैं।');
    }

    public function start(Request $request, Test $test, LibraryPracticeService $service, LibraryPracticeBridge $bridge, ExamEngineService $engine): RedirectResponse
    {
        try {
            $account = $this->account($request);
            $service->assertMembership($account, $bridge);
            $attempt = $service->start($account, $service->month($account), $test, $engine);
        } catch (\RuntimeException $e) {
            return back()->withErrors(['practice' => $e->getMessage()]);
        }

        return redirect()->route('library-practice.attempts.show', $attempt);
    }

    public function show(Request $request, TestAttempt $attempt, LibraryPracticeService $service, ExamEngineService $engine): View|RedirectResponse
    {
        $service->authorizeAttempt($this->account($request), $attempt);
        if ($attempt->status === 'evaluated') {
            return redirect()->route('library-practice.attempts.result', $attempt);
        }
        if ($engine->isExpired($attempt)) {
            $engine->submit($attempt);

            return redirect()->route('library-practice.attempts.result', $attempt);
        }
        $attempt->load(['test', 'attemptQuestions.question.options', 'answers']);
        $deadline = $attempt->started_at->copy()->addMinutes($attempt->test->duration_minutes);

        return view('library-practice.exam', compact('attempt', 'deadline'));
    }

    public function answer(Request $request, TestAttempt $attempt, LibraryPracticeService $service, ExamEngineService $engine): JsonResponse
    {
        $service->authorizeAttempt($this->account($request), $attempt);
        if ($engine->isExpired($attempt)) {
            $engine->submit($attempt);

            return response()->json(['expired' => true], 409);
        }
        $data = $request->validate(['question_id' => ['required', 'integer'], 'selected_option_id' => ['nullable', 'integer'], 'marked_for_review' => ['nullable', 'boolean']]);
        $engine->saveAnswer($attempt, (int) $data['question_id'], $data['selected_option_id'] ?? null, (bool) ($data['marked_for_review'] ?? false));

        return response()->json(['saved' => true]);
    }

    public function submit(Request $request, TestAttempt $attempt, LibraryPracticeService $service, ExamEngineService $engine): RedirectResponse
    {
        $service->authorizeAttempt($this->account($request), $attempt);
        $engine->submit($attempt);

        return redirect()->route('library-practice.attempts.result', $attempt);
    }

    public function result(Request $request, TestAttempt $attempt, LibraryPracticeService $service): View
    {
        $service->authorizeAttempt($this->account($request), $attempt);
        abort_unless($attempt->status === 'evaluated', 404);
        $attempt->load(['test.exam', 'attemptQuestions.question.options', 'answers.selectedOption']);
        $canReviewAnswers = $attempt->test->answer_visibility === 'immediate'
            || ($attempt->test->answer_visibility === 'scheduled' && $attempt->test->answers_available_at && now()->greaterThanOrEqualTo($attempt->test->answers_available_at));

        return view('library-practice.result', compact('attempt', 'canReviewAnswers'));
    }

    public function logout(Request $request, LibraryPracticeBridge $bridge): RedirectResponse
    {
        $a = $this->account($request);
        $identity = $bridge->identity((int) $a->library_student_id, $a->student_code, (int) $a->library_user_id);
        if ($identity) {
            $bridge->closePractice($identity, (string) $request->session()->get('library_practice_device_hash'), (string) $request->session()->get('library_practice_session_token'));
        }
        $request->session()->forget(['library_practice_account_id', 'library_practice_device_hash', 'library_practice_session_token']);
        $request->session()->regenerate();
        $request->session()->regenerateToken();

        return redirect()->away('https://cnetlibrary.mciedu.com/student/dashboard');
    }
}
