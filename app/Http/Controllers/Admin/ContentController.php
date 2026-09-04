<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ContentController extends Controller
{
    public function index(): View
    {
        return view('admin.content.index', [
            'subjects' => DB::table('subjects')->orderBy('sort_order')->orderBy('name')->get(),
            'topics' => DB::table('topics as t')->join('subjects as s', 's.id', '=', 't.subject_id')
                ->select('t.*', 's.name as subject_name')->orderBy('s.name')->orderBy('t.name')->get(),
            'exams' => DB::table('exams')->where('is_active', true)->orderBy('name')->get(),
            'questions' => DB::table('questions as q')->join('subjects as s', 's.id', '=', 'q.subject_id')
                ->leftJoin('topics as t', 't.id', '=', 'q.topic_id')
                ->select('q.id', 'q.question_text', 'q.question_text_hi', 'q.difficulty', 'q.is_published',
                    'q.verification_status', 's.name as subject_name', 't.name as topic_name')
                ->orderByDesc('q.id')->paginate(15, ['*'], 'questions_page'),
            'tests' => DB::table('tests as t')->leftJoin('exams as e', 'e.id', '=', 't.exam_id')
                ->select('t.*', 'e.name as exam_name')->orderByDesc('t.id')->limit(30)->get(),
            'series' => DB::table('test_series as ts')->leftJoin('exams as e', 'e.id', '=', 'ts.exam_id')
                ->select('ts.*', 'e.name as exam_name')->orderByDesc('ts.id')->limit(30)->get(),
            'counts' => [
                'subjects' => DB::table('subjects')->count(),
                'topics' => DB::table('topics')->count(),
                'questions' => DB::table('questions')->count(),
                'published' => DB::table('questions')->where('is_published', true)->count(),
                'tests' => DB::table('tests')->count(),
            ],
        ]);
    }

    public function storeSubject(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:subjects,name'],
            'name_hi' => ['required', 'string', 'max:255'],
        ]);

        DB::table('subjects')->insert([
            ...$data,
            'slug' => Str::slug($data['name']).'-'.Str::lower(Str::random(4)),
            'description' => null,
            'sort_order' => (int) DB::table('subjects')->max('sort_order') + 1,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return back()->with('success', 'Subject created.');
    }

    public function storeTopic(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'subject_id' => ['required', 'integer', 'exists:subjects,id'],
            'name' => ['required', 'string', 'max:255'],
            'name_hi' => ['required', 'string', 'max:255'],
        ]);

        DB::table('topics')->insert([
            ...$data,
            'parent_id' => null,
            'slug' => Str::slug($data['name']).'-'.Str::lower(Str::random(4)),
            'description' => null,
            'sort_order' => 0,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return back()->with('success', 'Topic created.');
    }

    public function storeQuestion(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'subject_id' => ['required', 'integer', 'exists:subjects,id'],
            'topic_id' => ['nullable', 'integer', 'exists:topics,id'],
            'exam_id' => ['nullable', 'integer', 'exists:exams,id'],
            'question_text' => ['required', 'string', 'max:5000'],
            'question_text_hi' => ['required', 'string', 'max:5000'],
            'explanation' => ['nullable', 'string', 'max:5000'],
            'explanation_hi' => ['nullable', 'string', 'max:5000'],
            'difficulty' => ['required', 'in:easy,medium,hard'],
            'options' => ['required', 'array', 'size:4'],
            'options.*' => ['required', 'string', 'max:2000'],
            'options_hi' => ['required', 'array', 'size:4'],
            'options_hi.*' => ['required', 'string', 'max:2000'],
            'correct_option' => ['required', 'integer', 'between:0,3'],
        ]);

        if (!empty($data['topic_id'])) {
            abort_unless(DB::table('topics')->where('id', $data['topic_id'])->where('subject_id', $data['subject_id'])->exists(), 422, 'Topic does not belong to subject.');
        }

        $hash = hash('sha256', Str::lower(trim($data['question_text'])).'|'.Str::lower(trim($data['question_text_hi'])));
        abort_if(DB::table('questions')->where('content_hash', $hash)->exists(), 422, 'Duplicate question.');

        DB::transaction(function () use ($data, $hash): void {
            $questionId = DB::table('questions')->insertGetId([
                'subject_id' => $data['subject_id'],
                'topic_id' => $data['topic_id'] ?? null,
                'question_text' => $data['question_text'],
                'question_text_hi' => $data['question_text_hi'],
                'explanation' => $data['explanation'] ?? null,
                'explanation_hi' => $data['explanation_hi'] ?? null,
                'question_type' => 'single_choice',
                'difficulty' => $data['difficulty'],
                'language' => 'bilingual',
                'is_current_affairs' => false,
                'source_name' => 'MCI Admin Manual',
                'source_confidence' => 100,
                'verification_status' => 'verified',
                'generation_method' => 'manual',
                'content_hash' => $hash,
                'auto_publish' => false,
                'is_published' => true,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            foreach ($data['options'] as $index => $option) {
                DB::table('question_options')->insert([
                    'question_id' => $questionId,
                    'option_text' => $option,
                    'option_text_hi' => $data['options_hi'][$index],
                    'is_correct' => $index === (int) $data['correct_option'],
                    'sort_order' => $index,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            if (!empty($data['exam_id'])) {
                DB::table('exam_question')->insert([
                    'exam_id' => $data['exam_id'],
                    'question_id' => $questionId,
                    'relevance_score' => 100,
                ]);
            }
        });

        return back()->with('success', 'Verified bilingual question published.');
    }

    public function generate(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'per_exam' => ['required', 'integer', 'between:1,5'],
            'questions' => ['required', 'integer', 'between:5,100'],
            'difficulty' => ['required', 'in:easy,medium,hard,mixed'],
            'type' => ['required', 'in:practice,mock,sectional,previous_year'],
        ]);

        $exit = Artisan::call('test-series:generate', [
            '--per-exam' => $data['per_exam'],
            '--questions' => $data['questions'],
            '--difficulty' => $data['difficulty'],
            '--type' => $data['type'],
        ]);

        return back()->with($exit === 0 ? 'success' : 'error',
            trim(Artisan::output()) ?: ($exit === 0 ? 'Test generation completed.' : 'Test generation failed.'));
    }

    public function toggleTest(int $test): RedirectResponse
    {
        $record = DB::table('tests')->where('id', $test)->first();
        abort_unless($record, 404);
        DB::table('tests')->where('id', $test)->update(['is_active' => !$record->is_active, 'updated_at' => now()]);
        return back()->with('success', 'Test status updated.');
    }

    public function toggleSeries(int $series): RedirectResponse
    {
        $record = DB::table('test_series')->where('id', $series)->first();
        abort_unless($record, 404);
        DB::table('test_series')->where('id', $series)->update(['is_active' => !$record->is_active, 'updated_at' => now()]);
        return back()->with('success', 'Test series status updated.');
    }
}
