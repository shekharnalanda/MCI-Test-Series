<?php

use App\Models\Question;
use App\Models\Test;
use App\Services\WikidataCountryFactImporter;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

function snapshotTable(string $table, ?int $maximumId = null, array $excluded = []): array
{
    $columns = array_values(array_diff(Schema::getColumnListing($table), $excluded));
    $query = DB::table($table)->select($columns);
    if ($maximumId !== null) {
        $query->where('id', '<=', $maximumId);
    }
    foreach ($columns as $column) {
        $query->orderBy($column);
    }
    $hash = hash_init('sha256');
    $count = 0;
    foreach ($query->cursor() as $row) {
        hash_update($hash, json_encode((array) $row, JSON_THROW_ON_ERROR)."\n");
        $count++;
    }

    return ['rows' => $count, 'sha256' => hash_final($hash)];
}

try {
    $root = $argv[1] ?? '';
    $private = $argv[2] ?? '';
    if (! is_file($root.'/artisan') || ! is_dir($private) || is_link($private) || PHP_VERSION_ID < 80300) {
        throw new RuntimeException('A valid application, private backup and PHP 8.3+ are required.');
    }
    require $root.'/vendor/autoload.php';
    $app = require $root.'/bootstrap/app.php';
    $app->make(Kernel::class)->bootstrap();
    $result = DB::transaction(function () use ($private): array {
        $protected = [];
        $tables = ['tests', 'question_test', 'questions', 'question_options', 'student_enrollments', 'student_enrollment_tests',
            'test_attempts', 'attempt_questions', 'attempt_answers', 'library_practice_selections', 'library_practice_months'];
        foreach ($tables as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }
            $maximumId = Schema::hasColumn($table, 'id') ? (int) DB::table($table)->max('id') : null;
            $excluded = $table === 'questions' ? ['usage_count', 'updated_at'] : [];
            $protected[$table] = ['maximum_id' => $maximumId, 'excluded' => $excluded, 'snapshot' => snapshotTable($table, $maximumId, $excluded)];
        }
        file_put_contents($private.'/protected-records.json', json_encode($protected, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR), LOCK_EX);
        $beforeTest = (int) Test::max('id');
        $beforeQuestion = (int) Question::max('id');
        // One verified new family is enough to prove live ingestion; never reprocess a bulk bank here.
        $import = app(WikidataCountryFactImporter::class)->import('legislature', 50);
        if ($import['accepted'] === 0 && Question::where('source_reference', 'wikidata-country-legislature')->count() === 0) {
            throw new RuntimeException('The new verified source page did not yield any questions.');
        }
        foreach ([
            'test-series:generate --questions=25 --type=practice --cycle=monthly --max-per-exam=10 --max-total=3',
            'test-series:generate --questions=100 --type=full_mock --cycle=monthly --max-per-exam=5 --max-total=1',
            'test-series:chapters --category=ssc --category=bihar-police --cycle=monthly --max-per-chapter=2 --max-total=3',
        ] as $command) {
            if (Artisan::call($command) !== 0) {
                throw new RuntimeException('Bounded generation failed.');
            }
            echo Artisan::output();
        }
        $created = Test::where('id', '>', $beforeTest)->with('questions')->get();
        foreach ($created as $test) {
            if ($test->questions->count() !== $test->total_questions || $test->questions->contains(fn ($q) => ! $q->is_active || ! $q->is_published || $q->verification_status !== 'verified')) {
                throw new RuntimeException('A new paper failed its complete verified question check.');
            }
            if ($test->topic_id !== null && $test->questions->contains(fn ($q) => $q->topic_id !== $test->topic_id || $q->subject_id !== $test->subject_id)) {
                throw new RuntimeException('A chapter contains a question from another topic.');
            }
        }
        $practice = $created->where('test_type', 'practice')->count();
        $mock = $created->where('test_type', 'full_mock')->count();
        $chapter = $created->where('test_type', 'topic')->count();
        if ($practice > 3 || $mock > 1 || $chapter > 3 || ($practice + $chapter) === 0) {
            throw new RuntimeException('The bounded generation proof failed.');
        }
        foreach ($protected as $table => $expected) {
            if (snapshotTable($table, $expected['maximum_id'], $expected['excluded']) !== $expected['snapshot']) {
                throw new RuntimeException('A protected old record changed: '.$table);
            }
        }
        $questionIds = Question::where('id', '>', $beforeQuestion)->pluck('id')->all();
        file_put_contents($private.'/created-content.json', json_encode(['test_ids' => $created->modelKeys(), 'question_ids' => $questionIds], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR), LOCK_EX);
        printf("VERIFIED | old tests=%d | old questions=%d | attempts/enrollments/quota snapshots unchanged\n", $protected['tests']['snapshot']['rows'], $protected['questions']['snapshot']['rows']);
        printf("VERIFIED | imported=%d | complete new practice=%d mock=%d chapter=%d | verified only\n", $import['accepted'], $practice, $mock, $chapter);

        return ['imported' => $import['accepted'], 'practice' => $practice, 'mock' => $mock, 'chapter' => $chapter, 'next_offset' => $import['next_offset']];
    });
    // Save the rotation cursor only after the question/test transaction has committed.
    Cache::forever('mci-question-bank-refresh-cursor', ['family' => 1, 'offsets' => ['legislature' => $result['next_offset']]]);
    printf("AUTOMATION_COMMITTED | imported=%d practice=%d mock=%d chapter=%d\n", $result['imported'], $result['practice'], $result['mock'], $result['chapter']);
} catch (Throwable $error) {
    fwrite(STDERR, 'STOPPED: Automation verification failed: '.$error->getMessage()."\n");
    exit(1);
}
