<?php

namespace Tests\Feature;

use App\Models\ContentSource;
use App\Models\Exam;
use App\Models\ExamCategory;
use App\Models\Question;
use App\Models\StudentProfile;
use App\Models\Subject;
use App\Models\Test;
use App\Models\Topic;
use App\Models\User;
use App\Services\AutomaticTestGenerator;
use App\Services\ChapterTestGenerator;
use App\Services\ExamEngineService;
use App\Services\TrustedSourceHealthService;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class AutomationRefreshTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function curriculum(string $slug = 'ssc-cgl'): array
    {
        $category = ExamCategory::firstOrCreate(['slug' => 'ssc'], ['name' => 'SSC']);
        $exam = Exam::create(['name' => $slug, 'slug' => $slug, 'exam_category_id' => $category->id, 'is_active' => true]);
        $subject = Subject::firstOrCreate(['slug' => 'mathematics'], ['name' => 'Mathematics', 'is_active' => true]);
        $exam->subjects()->attach($subject);
        $topic = Topic::firstOrCreate(['slug' => 'percentage'], ['subject_id' => $subject->id, 'name' => 'Percentage', 'is_active' => true]);
        for ($i = 0; $i < 60; $i++) {
            $question = Question::create(['subject_id' => $subject->id, 'topic_id' => $topic->id,
                'question_text' => $slug.' question '.$i, 'content_hash' => hash('sha256', $slug.'-'.$i),
                'difficulty' => ['easy', 'medium', 'hard'][$i % 3], 'verification_status' => 'verified', 'is_published' => true, 'is_active' => true]);
            $question->exams()->attach($exam);
            for ($option = 0; $option < 4; $option++) {
                $question->options()->create(['option_text' => 'Option '.$option, 'is_correct' => $option === 0, 'sort_order' => $option + 1]);
            }
        }

        return [$exam, $topic];
    }

    public function test_monthly_generation_adds_distinct_sets_and_keeps_old_tests_and_attempt_snapshots(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 3));
        [$exam] = $this->curriculum();
        $generator = app(AutomaticTestGenerator::class);
        $old = $generator->generate($exam, 10, 'mixed', 'practice', 1, 1);
        $student = StudentProfile::create(['user_id' => User::factory()->create()->id, 'student_code' => 'AUTO-QA', 'status' => 'active']);
        $attempt = app(ExamEngineService::class)->start($old, $student);
        $oldAttributes = $old->getAttributes();
        $oldPivots = DB::table('question_test')->where('test_id', $old->id)->get()->toArray();
        $snapshots = $attempt->attemptQuestions()->get()->toArray();
        $this->artisan('test-series:generate --questions=10 --min-pool-multiple=1 --cycle=monthly --max-per-exam=1 --max-total=1')->assertSuccessful();
        $this->assertSame(1, Test::count());
        $this->travelTo(now()->setDate(2026, 11, 3));
        $this->artisan('test-series:generate --questions=10 --min-pool-multiple=1 --cycle=monthly --max-per-exam=1 --max-total=1')->assertSuccessful();
        $this->assertSame(2, Test::count());
        $this->assertSame($oldAttributes, $old->fresh()->getAttributes());
        $this->assertEquals($oldPivots, DB::table('question_test')->where('test_id', $old->id)->get()->toArray());
        $this->assertSame($snapshots, $attempt->attemptQuestions()->get()->toArray());
        $this->assertNotEqualsCanonicalizing($old->questions->modelKeys(), Test::latest('id')->firstOrFail()->questions->modelKeys());
    }

    public function test_bounded_runs_rotate_exams_instead_of_always_filling_the_first_exam(): void
    {
        $this->freezeTime();
        [$first] = $this->curriculum();
        [$second] = $this->curriculum('ssc-chsl');
        $command = 'test-series:generate --questions=10 --min-pool-multiple=1 --cycle=monthly --max-per-exam=2 --max-total=1';
        $this->artisan($command)->assertSuccessful();
        $this->assertSame(1, Test::count());
        $this->assertSame($first->id, Test::firstOrFail()->exam_id);
        $this->artisan($command)->assertSuccessful();
        $this->assertSame(2, Test::count());
        $this->assertSame($second->id, Test::latest('id')->firstOrFail()->exam_id);
        $this->artisan('test-series:generate --cycle=invalid --max-total=1')->assertFailed();
        $this->assertSame(2, Test::count());
    }

    public function test_service_enforces_the_monthly_cap_even_without_the_command(): void
    {
        $this->freezeTime();
        [$exam] = $this->curriculum();
        $generator = app(AutomaticTestGenerator::class);
        $generator->generate($exam, 10, 'mixed', 'practice', 1, 1);
        try {
            $generator->generate($exam, 10, 'mixed', 'practice', 1, 1);
            $this->fail('The monthly cap must be enforced under the exam lock.');
        } catch (RuntimeException $error) {
            $this->assertSame('Monthly test limit reached.', $error->getMessage());
        }
        $this->assertSame(1, Test::count());
    }

    public function test_monthly_chapter_sets_are_distinct_and_never_replace_an_old_paper(): void
    {
        $this->freezeTime();
        [$exam, $topic] = $this->curriculum();
        $generator = app(ChapterTestGenerator::class);
        $old = $generator->generate($exam, $topic, 25);
        $attributes = $old->getAttributes();
        $ids = $old->questions->modelKeys();
        $this->artisan('test-series:chapters --category=ssc --cycle=monthly --max-per-chapter=2 --max-total=1')->assertSuccessful();
        $this->artisan('test-series:chapters --category=ssc --cycle=monthly --max-per-chapter=2 --max-total=1')->assertSuccessful();
        $this->assertSame(2, Test::count());
        $this->assertSame($attributes, $old->fresh()->getAttributes());
        $this->assertSame($ids, $old->fresh()->questions->modelKeys());
        $new = Test::latest('id')->firstOrFail();
        $this->assertSame(25, $new->questions->count());
        $this->assertSame([$topic->id], $new->questions->pluck('topic_id')->unique()->all());
        $this->assertNotEqualsCanonicalizing($ids, $new->questions->modelKeys());
    }

    public function test_an_identical_chapter_set_is_not_added_when_no_new_selection_is_possible(): void
    {
        [$exam, $topic] = $this->curriculum();
        $generator = app(ChapterTestGenerator::class);
        $old = $generator->generate($exam, $topic, 60);
        try {
            $generator->generate($exam, $topic, 60, 2);
            $this->fail('Identical papers must not be added.');
        } catch (RuntimeException $error) {
            $this->assertStringContainsString('identical chapter set', $error->getMessage());
        }
        $this->assertSame(1, Test::count());
        $this->assertSame(60, $old->fresh()->questions->count());
    }

    private function fakeFacts(int $count = 50): array
    {
        return ['results' => ['bindings' => array_map(fn ($i) => [
            'country' => ['value' => 'https://www.wikidata.org/entity/Q'.($i + 100)],
            'countryLabelEn' => ['value' => 'Country '.$i], 'countryLabelHi' => ['value' => 'देश '.$i],
            'answer' => ['value' => 'https://www.wikidata.org/entity/Q'.($i + 1000)],
            'answerLabelEn' => ['value' => 'Parliament '.$i], 'answerLabelHi' => ['value' => 'संसद '.$i],
        ], range(1, $count))]];
    }

    public function test_refresh_imports_new_verified_bilingual_questions_rotates_pages_and_preserves_existing_content(): void
    {
        $this->seed();
        Http::preventStrayRequests();
        Http::fake(['https://www.wikidata.org*' => Http::response('ok'), 'https://query.wikidata.org/*' => Http::response($this->fakeFacts())]);
        $code = Artisan::call('question-bank:refresh', ['--limit' => 50]);
        $this->assertSame(0, $code, Artisan::output());
        $questions = Question::where('source_reference', 'wikidata-country-legislature')->get();
        $this->assertCount(50, $questions);
        $this->assertSame(['verified'], $questions->pluck('verification_status')->unique()->all());
        $this->assertCount(4, $questions->first()->options);
        $this->assertSame(1, $questions->first()->options->where('is_correct', true)->count());
        $this->assertNotEmpty($questions->first()->question_text_hi);
        $this->assertSame(['family' => 1, 'offsets' => ['legislature' => 50]], Cache::get('mci-question-bank-refresh-cursor'));
        $questions->first()->update(['explanation' => 'Existing explanation must remain unchanged.']);
        Cache::forever('mci-question-bank-refresh-cursor', ['family' => 0, 'offsets' => ['legislature' => 50]]);
        $code = Artisan::call('question-bank:refresh', ['--limit' => 50]);
        $this->assertSame(0, $code, Artisan::output());
        $this->assertSame(50, Question::where('source_reference', 'wikidata-country-legislature')->count());
        $this->assertSame('Existing explanation must remain unchanged.', $questions->first()->fresh()->explanation);
        Http::assertSent(fn (Request $request) => str_contains($request['query'] ?? '', 'OFFSET 50') && str_contains($request['query'], 'FILTER(?otherAnswer != ?answer)'));
    }

    public function test_failed_refresh_keeps_cursor_and_a_short_page_resets_only_its_family(): void
    {
        $this->seed();
        Http::preventStrayRequests();
        $state = ['family' => 0, 'offsets' => ['legislature' => 50]];
        Cache::forever('mci-question-bank-refresh-cursor', $state);
        Http::fake(['https://www.wikidata.org*' => Http::response('ok'), 'https://query.wikidata.org/*' => Http::sequence()->push('down', 503)->push('down', 503)->push(['results' => ['bindings' => []]])]);
        $this->artisan('question-bank:refresh')->assertFailed();
        $this->assertSame($state, Cache::get('mci-question-bank-refresh-cursor'));
        $code = Artisan::call('question-bank:refresh');
        $this->assertSame(0, $code, Artisan::output());
        $this->assertSame(['family' => 1, 'offsets' => ['legislature' => 0]], Cache::get('mci-question-bank-refresh-cursor'));
        $this->assertSame(0, Question::where('source_reference', 'wikidata-country-legislature')->count());
        Http::assertSentCount(5);
    }

    public function test_a_second_refresh_exits_without_network_requests_while_the_lock_is_held(): void
    {
        Http::preventStrayRequests();
        Http::fake();
        $lock = Cache::lock('mci-question-bank-refresh', 300);
        $this->assertTrue($lock->get());
        try {
            $this->artisan('question-bank:refresh')->expectsOutput('Question bank refresh already running.')->assertSuccessful();
            Http::assertNothingSent();
        } finally {
            $lock->release();
        }
    }

    public function test_refresh_pages_are_sorted_into_gk_science_and_computer_topics(): void
    {
        $this->seed();
        Http::preventStrayRequests();
        $rows = $this->fakeFacts(4)['results']['bindings'];
        $rows = array_map(function (array $row): array {
            foreach (['item', 'software', 'book'] as $entity) {
                $row[$entity] = $row['country'];
                $row[$entity.'LabelEn'] = $row['countryLabelEn'];
                $row[$entity.'LabelHi'] = $row['countryLabelHi'];
            }
            foreach (['person', 'developer', 'author'] as $answer) {
                $row[$answer] = $row['answer'];
                $row[$answer.'LabelEn'] = $row['answerLabelEn'];
                $row[$answer.'LabelHi'] = $row['answerLabelHi'];
            }

            return $row;
        }, $rows);
        Http::fake(['https://www.wikidata.org*' => Http::response('ok'), 'https://query.wikidata.org/*' => Http::response(['results' => ['bindings' => $rows]])]);
        Cache::forever('mci-question-bank-refresh-cursor', ['family' => 15, 'offsets' => ['discoveries' => 50, 'software' => 50, 'books' => 50]]);
        foreach (['wikidata-discovery-inventor' => 'General Science', 'wikidata-software-developer' => 'Computer Knowledge', 'wikidata-book-author' => 'General Knowledge'] as $reference => $subject) {
            $this->artisan('question-bank:refresh')->assertSuccessful();
            $questions = Question::where('source_reference', $reference)->get();
            $this->assertCount(4, $questions);
            $this->assertSame([$subject], $questions->pluck('subject.name')->unique()->all());
            $this->assertSame(['verified'], $questions->pluck('verification_status')->unique()->all());
            $this->assertSame(4, $questions->first()->options->count());
        }
        Http::assertSent(fn (Request $request) => str_contains($request['query'] ?? '', 'OFFSET 50') && str_contains($request['query'], 'FILTER(?otherAnswer != ?person)'));
        $this->assertSame(0, Cache::get('mci-question-bank-refresh-cursor')['family']);
    }

    public function test_rbi_health_probes_the_actual_secure_feed_and_rejects_an_unrelated_host(): void
    {
        $this->seed();
        $source = ContentSource::where('slug', 'reserve-bank-of-india')->firstOrFail();
        Http::preventStrayRequests();
        Http::fake([$source->feed_url => Http::response('<rss/>')]);
        $this->assertTrue(app(TrustedSourceHealthService::class)->check($source)['healthy']);
        Http::assertSent(fn (Request $request) => $request->url() === $source->feed_url);
        Http::fake();
        $source->update(['feed_url' => 'https://unrelated.example/feed.xml']);
        $this->assertFalse(app(TrustedSourceHealthService::class)->check($source)['healthy']);
        Http::assertNothingSent();
    }

    public function test_scheduler_keeps_small_distinct_batches_without_background_jobs(): void
    {
        $events = collect(app(Schedule::class)->events());
        $refresh = $events->first(fn ($event) => str_contains($event->command ?? '', 'question-bank:refresh'));
        $practice = $events->first(fn ($event) => str_contains($event->command ?? '', '--type=practice'));
        $mock = $events->first(fn ($event) => str_contains($event->command ?? '', '--type=full_mock'));
        $this->assertSame('40 * * * *', $refresh->expression);
        $this->assertSame('0 * * * *', $practice->expression);
        $this->assertSame('5 * * * *', $mock->expression);
        $this->assertStringContainsString('--max-total=3', $practice->command);
        $this->assertStringContainsString('--cycle=monthly', $mock->command);
        $this->assertTrue($events->every(fn ($event) => $event->withoutOverlapping && ! $event->runInBackground));
    }
}
