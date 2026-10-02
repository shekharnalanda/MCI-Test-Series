<?php

use App\Models\Exam;
use App\Models\Question;
use App\Models\Test;
use App\Models\TestAttempt;
use App\Models\Topic;
use App\Services\CurrentAffairsTopicMappingService;
use App\Services\TestCatalogService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\ViewErrorBag;

try {
    $root = $argv[1] ?? '';
    $backupDirectory = $argv[2] ?? '';
    if (! is_file($root.'/artisan') || ! is_dir($backupDirectory) || is_link($backupDirectory) || PHP_VERSION_ID < 80300) {
        throw new RuntimeException('An application root, private backup directory and PHP 8.3+ are required.');
    }
    require $root.'/vendor/autoload.php';
    $app = require $root.'/bootstrap/app.php';
    $app->make(Kernel::class)->bootstrap();
    view()->share('errors', new ViewErrorBag);
    $catalog = $app->make(TestCatalogService::class);
    $count = $app->make(CurrentAffairsTopicMappingService::class)->mapExistingRbiQuestions(
        function (array $originals, int $topicId) use ($backupDirectory): void {
            if ($originals !== [] && array_keys($originals) !== range(6, 16)) {
                throw new RuntimeException('The reviewed set of 11 RBI questions changed; stop for review.');
            }
            $data = json_encode(['original_topics' => $originals, 'target_topic_id' => $topicId], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT);
            $path = $backupDirectory.'/question-topics.json';
            $handle = fopen($path, 'x');
            if ($handle === false) {
                throw new RuntimeException('The private question mapping backup cannot be created.');
            }
            try {
                if (! chmod($path, 0600) || fwrite($handle, $data) !== strlen($data) || ! fflush($handle)) {
                    throw new RuntimeException('The private mapping backup is incomplete.');
                }
            } finally {
                fclose($handle);
            }
            if (file_get_contents($path) !== $data) {
                throw new RuntimeException('The private mapping backup failed verification.');
            }
        },
        function (Topic $topic, int $count) use ($catalog): void {
            if (Question::whereIn('id', range(6, 16))->where('topic_id', $topic->id)->count() !== 11) {
                throw new RuntimeException('All 11 reviewed RBI questions must have the banking topic.');
            }
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
                    foreach ($initial['subjects'] as $subject) {
                        $data = $catalog->browse($scope(), ['exam' => $exam->id, 'subject' => $subject->id]);
                        if ($data['topics']->isEmpty()) {
                            throw new RuntimeException('A subject in the reviewed exams has no mapped chapter.');
                        }
                        foreach ($data['topics'] as $chapter) {
                            $filtered = $catalog->browse($scope(), ['exam' => $exam->id, 'subject' => $subject->id, 'topic' => $chapter->id]);
                            if ($filtered['tests']->total() === 0 || $filtered['tests']->contains(fn (Test $test): bool => $test->exam_id !== $exam->id)) {
                                throw new RuntimeException('Chapter filtering returned missing or unrelated tests.');
                            }
                        }
                    }
                    $data = $catalog->browse($scope(), ['exam' => $exam->id, 'subject' => $topic->subject_id, 'topic' => $topic->id]);
                    if (! $data['topics']->contains('id', $topic->id) || $data['tests']->total() === 0) {
                        throw new RuntimeException('The banking chapter is missing from the catalog.');
                    }
                    $html = $panel === 'paid'
                        ? view('student.tests.index', array_merge($data, ['demoAccess' => true, 'enrollment' => null, 'selectedIds' => [], 'attemptedIds' => [], 'completedIds' => []]))->render()
                        : view('library-practice.index', array_merge($data, [
                            'account' => (object) ['student_name' => 'Chapter verification', 'student_code' => 'QA'],
                            'month' => (object) ['period' => now()->format('Y-m')], 'eligible' => false,
                            'selections' => collect(), 'selectedTests' => collect(), 'completed' => 0,
                            'attempts' => TestAttempt::whereRaw('1 = 0')->paginate(10, ['*'], 'results_page'),
                        ]))->render();
                    if (! str_contains($html, 'Economy and Banking') || ! str_contains($html, 'catalog-topic')) {
                        throw new RuntimeException('The full panel did not render the banking chapter.');
                    }
                    printf("VERIFIED | %s | %s | every subject checked | banking chapter | tests=%d\n", $panel, $name, $data['tests']->total());
                }
            }
        },
    );
    echo 'TOPIC_MAPPING_COMMITTED | mapped='.$count." | reviewed RBI questions=11 | both panels rendered\n";
} catch (Throwable $error) {
    fwrite(STDERR, 'STOPPED: Topic mapping rolled back: '.$error->getMessage()."\n");
    exit(1);
}
