<?php

use App\Models\Exam;
use App\Models\Question;
use App\Models\Test;
use App\Models\TestAttempt;
use App\Services\TestCatalogService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ViewErrorBag;

try {
    $root = $argv[1] ?? '';
    $private = $argv[2] ?? '';
    if (! is_file($root.'/artisan') || ! is_dir($private) || is_link($private) || PHP_VERSION_ID < 80300) {
        throw new RuntimeException('An application root, private backup directory and PHP 8.3+ are required.');
    }
    require $root.'/vendor/autoload.php';
    $app = require $root.'/bootstrap/app.php';
    $app->make(Kernel::class)->bootstrap();
    view()->share('errors', new ViewErrorBag);
    $result = DB::transaction(function () use ($private): array {
        $before = Test::pluck('id')->all();
        $studentCounts = [];
        foreach (['student_enrollments', 'student_enrollment_tests', 'test_attempts', 'library_practice_selections', 'library_practice_months'] as $table) {
            if (Schema::hasTable($table)) {
                $studentCounts[$table] = DB::table($table)->count();
            }
        }
        $questions = Question::whereHas('exams.category', fn (Builder $q) => $q->whereIn('slug', ['ssc', 'bihar-police']))
            ->orderBy('id')->lockForUpdate()->get(['id', 'usage_count']);
        $snapshot = json_encode(['previous_test_ids' => $before, 'previous_series_ids' => DB::table('test_series')->pluck('id')->all(),
            'question_usage' => $questions->pluck('usage_count', 'id')->all(), 'student_counts' => $studentCounts], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
        $path = $private.'/content-before.json';
        $stream = fopen($path, 'x');
        if ($stream === false) {
            throw new RuntimeException('The private content backup could not be opened.');
        }
        try {
            if (! chmod($path, 0600) || fwrite($stream, $snapshot) !== strlen($snapshot) || ! fflush($stream)) {
                throw new RuntimeException('The private content backup is incomplete.');
            }
        } finally {
            fclose($stream);
        }
        if (file_get_contents($path) !== $snapshot) {
            throw new RuntimeException('The private content backup failed verification.');
        }
        $exit = Artisan::call('test-series:chapters', ['--category' => ['ssc', 'bihar-police'], '--no-interaction' => true]);
        if ($exit !== 0) {
            throw new RuntimeException('Chapter generation did not finish successfully.');
        }
        $papers = Test::where('test_type', 'topic')->where('is_active', true)
            ->whereHas('exam.category', fn (Builder $q) => $q->whereIn('slug', ['ssc', 'bihar-police']))->get();
        foreach ($papers as $paper) {
            if ($paper->total_questions < 10 || ! Test::whereKey($paper->id)->chapter((int) $paper->subject_id, (int) $paper->topic_id)->exists()) {
                throw new RuntimeException('A chapter paper is incomplete or contains unrelated questions.');
            }
        }
        $catalog = app(TestCatalogService::class);
        $scopes = [
            'paid' => fn (): Builder => Test::where('is_active', true),
            'library' => fn (): Builder => Test::where('is_active', true)
                ->where(fn (Builder $q) => $q->whereNull('available_from')->orWhere('available_from', '<=', now()))
                ->where(fn (Builder $q) => $q->whereNull('available_until')->orWhere('available_until', '>=', now()))
                ->whereHas('questions', fn (Builder $q) => $q->where('questions.is_active', true)->where('questions.is_published', true)),
        ];
        foreach (['SSC CGL', 'Bihar Police SI'] as $name) {
            $exam = Exam::where('name', $name)->firstOrFail();
            foreach ($scopes as $panel => $scope) {
                $initial = $catalog->browse($scope(), ['exam' => $exam->id]);
                $chapters = 0;
                foreach ($initial['subjects'] as $subject) {
                    $data = $catalog->browse($scope(), ['exam' => $exam->id, 'subject' => $subject->id]);
                    foreach ($data['topics'] as $topic) {
                        $filtered = $catalog->browse($scope(), ['exam' => $exam->id, 'subject' => $subject->id, 'topic' => $topic->id]);
                        if ($filtered['tests']->total() === 0 || $filtered['tests']->contains(fn (Test $t): bool => $t->exam_id !== $exam->id || $t->topic_id !== $topic->id || $t->test_type !== 'topic')) {
                            throw new RuntimeException('Chapter filtering returned missing, mixed or unrelated tests.');
                        }
                        $html = $panel === 'paid'
                            ? view('student.tests.index', array_merge($filtered, ['demoAccess' => true, 'enrollment' => null, 'selectedIds' => [], 'attemptedIds' => [], 'completedIds' => []]))->render()
                            : view('library-practice.index', array_merge($filtered, [
                                'account' => (object) ['student_name' => 'Chapter verification', 'student_code' => 'QA'],
                                'month' => (object) ['period' => now()->format('Y-m')], 'eligible' => false,
                                'selections' => collect(), 'selectedTests' => collect(), 'completed' => 0,
                                'attempts' => TestAttempt::whereRaw('1 = 0')->paginate(10, ['*'], 'results_page'),
                            ]))->render();
                        if (! str_contains($html, 'catalog-topic') || ! str_contains($html, e($filtered['tests']->first()->title))) {
                            throw new RuntimeException('The full panel did not render its chapter paper.');
                        }
                        $chapters++;
                    }
                }
                if ($chapters === 0) {
                    throw new RuntimeException('No chapter tests are available for a reviewed exam.');
                }
                printf("VERIFIED | %s | %s | chapters=%d | every paper contains only its chapter\n", $panel, $name, $chapters);
            }
        }
        foreach ($studentCounts as $table => $count) {
            if (DB::table($table)->count() !== $count) {
                throw new RuntimeException('Generation unexpectedly wrote a student or quota record.');
            }
        }
        $created = Test::whereNotIn('id', $before)->pluck('id')->all();
        $manifest = json_encode(['created_test_ids' => $created], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
        if (file_put_contents($private.'/created-tests.json', $manifest, LOCK_EX) !== strlen($manifest)) {
            throw new RuntimeException('The new content manifest could not be saved.');
        }

        return ['created' => count($created), 'total' => $papers->count(), 'exams' => $papers->pluck('exam_id')->unique()->count()];
    });
    printf("CHAPTER_TESTS_COMMITTED | created=%d | complete chapter papers=%d | exams=%d | no student or quota records written\n", $result['created'], $result['total'], $result['exams']);
} catch (Throwable $error) {
    fwrite(STDERR, 'STOPPED: Chapter generation rolled back: '.$error->getMessage()."\n");
    exit(1);
}
