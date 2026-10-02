<?php

use App\Models\Exam;
use App\Models\Test;
use App\Models\TestAttempt;
use App\Services\TestCatalogService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\ViewErrorBag;

try {
    $root = $argv[1] ?? '';
    if (! is_file($root.'/artisan') || PHP_VERSION_ID < 80300) {
        throw new RuntimeException('The application root and PHP 8.3+ are required.');
    }
    require $root.'/vendor/autoload.php';
    $app = require $root.'/bootstrap/app.php';
    $app->make(Kernel::class)->bootstrap();
    view()->share('errors', new ViewErrorBag);
    $catalog = $app->make(TestCatalogService::class);
    $scopes = [
        'paid' => fn (): Builder => Test::where('is_active', true),
        'library' => fn (): Builder => Test::where('is_active', true)
            ->where(fn (Builder $q) => $q->whereNull('available_from')->orWhere('available_from', '<=', now()))
            ->where(fn (Builder $q) => $q->whereNull('available_until')->orWhere('available_until', '>=', now()))
            ->whereHas('questions', fn (Builder $q) => $q->where('questions.is_active', true)->where('questions.is_published', true)),
    ];
    foreach (array_slice($argv, 2) ?: ['SSC CGL', 'Bihar Police SI'] as $name) {
        $exam = Exam::where('name', $name)->firstOrFail();
        foreach ($scopes as $panel => $scope) {
            $started = microtime(true);
            $data = $catalog->browse($scope(), ['exam' => $exam->id]);
            $actualExamIds = Exam::whereIn('id', $data['exams']->pluck('id'))->pluck('exam_category_id')->unique()->all();
            if ($actualExamIds !== [$exam->exam_category_id] || $data['tests']->contains(fn (Test $test): bool => $test->exam_id !== $exam->id)) {
                throw new RuntimeException('Category/exam isolation failed: '.$panel.' '.$name);
            }
            $filters = ['exam' => $exam->id];
            if ($subject = $data['subjects']->first()) {
                $filters['subject'] = $subject->id;
                $data = $catalog->browse($scope(), $filters);
                if ($topic = $data['topics']->first()) {
                    $filters['topic'] = $topic->id;
                    $data = $catalog->browse($scope(), $filters);
                }
            }
            if ($data['tests']->contains(fn (Test $test): bool => $test->exam_id !== $exam->id)) {
                throw new RuntimeException('Subject/topic isolation failed.');
            }
            $partial = view('layouts.test-catalog-filters', array_merge($data, ['catalogRoute' => route($panel === 'paid' ? 'student.tests.index' : 'library-practice.index')]))->render();
            if (! str_contains($partial, 'data-test-catalog') || ! str_contains($partial, 'catalog-topic')) {
                throw new RuntimeException('Catalog form did not render.');
            }
            if ($panel === 'paid') {
                $html = view('student.tests.index', array_merge($data, ['demoAccess' => true, 'enrollment' => null, 'selectedIds' => [], 'attemptedIds' => [], 'completedIds' => []]))->render();
            } else {
                $html = view('library-practice.index', array_merge($data, [
                    'account' => (object) ['student_name' => 'Catalog verification', 'student_code' => 'QA'],
                    'month' => (object) ['period' => now()->format('Y-m')], 'eligible' => false,
                    'selections' => collect(), 'selectedTests' => collect(), 'completed' => 0,
                    'attempts' => TestAttempt::whereRaw('1 = 0')->paginate(10, ['*'], 'results_page'),
                ]))->render();
            }
            if (! str_contains($html, 'catalog-category')) {
                throw new RuntimeException('Panel did not render.');
            }
            printf("VERIFIED | %s | %s | category exams=%d | subjects=%d | topics=%d | matching tests=%d | %.0f ms\n", $panel, $name, $data['exams']->count(), $data['subjects']->count(), $data['topics']->count(), $data['tests']->total(), (microtime(true) - $started) * 1000);
        }
    }
    echo "CATALOG_LIVE_CHECK_OK | both panels rendered | no student or quota records written\n";

} catch (Throwable $error) {
    fwrite(STDERR, 'STOPPED: Catalog verification failed: '.$error->getMessage()."\n");
    exit(1);
}
