<?php

namespace Tests\Feature\Services;

use App\Models\Exam;
use App\Models\ExamCategory;
use App\Models\Question;
use App\Models\StudentProfile;
use App\Models\Subject;
use App\Models\Test;
use App\Models\TestAttempt;
use App\Models\TestSeries;
use App\Models\Topic;
use App\Models\User;
use App\Services\ChapterTestGenerator;
use App\Services\ExamEngineService;
use App\Services\TestCatalogService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class ChapterTestGeneratorTest extends TestCase
{
    use RefreshDatabase;

    private function curriculum(): array
    {
        $category = ExamCategory::create(['name' => 'SSC', 'slug' => 'ssc']);
        $exam = Exam::create(['name' => 'SSC CGL', 'slug' => 'ssc-cgl', 'exam_category_id' => $category->id, 'is_active' => true]);
        $subject = Subject::create(['name' => 'Mathematics', 'slug' => 'mathematics']);
        $exam->subjects()->attach($subject);
        $topic = Topic::create(['name' => 'Percentage', 'slug' => 'percentage', 'subject_id' => $subject->id, 'is_active' => true]);

        return [$exam, $topic];
    }

    private function questions(Exam $exam, Topic $topic, int $count = 10, array $attributes = []): array
    {
        $ids = [];
        for ($i = 0; $i < $count; $i++) {
            $question = Question::create(array_merge([
                'subject_id' => $topic->subject_id, 'topic_id' => $topic->id,
                'question_text' => 'Percentage '.str()->uuid(), 'content_hash' => hash('sha256', (string) str()->uuid()),
                'is_active' => true, 'is_published' => true, 'verification_status' => 'verified',
            ], $attributes));
            $question->exams()->attach($exam);
            for ($option = 0; $option < 4; $option++) {
                $question->options()->create(['option_text' => 'Option '.$option, 'is_correct' => $option === 0, 'sort_order' => $option + 1]);
            }
            $ids[] = $question->id;
        }

        return $ids;
    }

    public function test_generation_uses_only_verified_aligned_questions_with_four_valid_options(): void
    {
        [$exam, $topic] = $this->curriculum();
        $ids = $this->questions($exam, $topic);
        $this->questions($exam, $topic, 1, ['verification_status' => 'pending']);
        $this->questions($exam, $topic, 1, ['is_published' => false]);
        $this->questions($exam, $topic, 1, ['is_active' => false]);
        $bad = $this->questions($exam, $topic, 1)[0];
        Question::findOrFail($bad)->options()->firstOrFail()->update(['is_correct' => false]);
        $missing = $this->questions($exam, $topic, 1)[0];
        Question::findOrFail($missing)->options()->firstOrFail()->delete();
        $other = Topic::create(['subject_id' => $topic->subject_id, 'name' => 'Profit', 'slug' => 'profit']);
        $this->questions($exam, $other);

        $test = app(ChapterTestGenerator::class)->generate($exam, $topic, 10);

        $this->assertSame($ids, $test->questions->modelKeys());
        $this->assertSame($topic->id, $test->topic_id);
        $this->assertSame($topic->subject_id, $test->subject_id);
        $this->assertSame(10, $test->total_questions);
        $this->assertSame('topic', $test->test_type);
        $this->assertSame(1, Test::whereKey($test->id)->chapter($topic->subject_id, $topic->id)->count());
    }

    public function test_an_insufficient_pool_creates_no_incomplete_test_or_series(): void
    {
        [$exam, $topic] = $this->curriculum();
        $this->questions($exam, $topic, 9);
        try {
            app(ChapterTestGenerator::class)->generate($exam, $topic, 10);
            $this->fail('An incomplete chapter test must not be created.');
        } catch (RuntimeException $error) {
            $this->assertStringContainsString('Only 9 eligible chapter questions', $error->getMessage());
        }
        $this->assertSame(0, Test::count());
        $this->assertSame(0, TestSeries::count());
        $this->assertSame(0, (int) Question::sum('usage_count'));
    }

    public function test_repeated_generation_reuses_the_test_and_the_attempt_contains_only_its_chapter(): void
    {
        [$exam, $topic] = $this->curriculum();
        $ids = $this->questions($exam, $topic);
        $generator = app(ChapterTestGenerator::class);
        $test = $generator->generate($exam, $topic, 10);
        $this->assertSame($test->id, $generator->generate($exam, $topic, 10)->id);
        $this->assertSame(1, Test::count());
        $this->assertSame(10, (int) Question::sum('usage_count'));
        $student = StudentProfile::create(['user_id' => User::factory()->create()->id, 'student_code' => 'CHAPTER-QA', 'status' => 'active']);
        $attempt = app(ExamEngineService::class)->start($test, $student);
        $this->assertEqualsCanonicalizing($ids, $attempt->attemptQuestions()->pluck('question_id')->all());
        $this->assertSame(10, $attempt->total_questions);
    }

    public function test_a_contaminated_chapter_is_hidden_and_cannot_start_a_new_attempt(): void
    {
        [$exam, $topic] = $this->curriculum();
        $ids = $this->questions($exam, $topic);
        $test = app(ChapterTestGenerator::class)->generate($exam, $topic, 10);
        $other = Topic::create(['subject_id' => $topic->subject_id, 'name' => 'Profit', 'slug' => 'profit']);
        Question::findOrFail($ids[0])->update(['topic_id' => $other->id]);
        $mixed = Test::create(['exam_id' => $exam->id, 'title' => 'Mixed Set', 'total_questions' => 10]);
        $mixed->questions()->attach($ids);
        $catalog = app(TestCatalogService::class)->browse(Test::where('is_active', true), ['exam' => $exam->id, 'subject' => $topic->subject_id, 'topic' => $topic->id]);
        $this->assertSame(0, $catalog['tests']->total());
        $student = StudentProfile::create(['user_id' => User::factory()->create()->id, 'student_code' => 'CONTAMINATED-QA', 'status' => 'active']);
        try {
            app(ExamEngineService::class)->start($test, $student);
            $this->fail('A contaminated chapter must not start.');
        } catch (RuntimeException $error) {
            $this->assertStringContainsString('another chapter', $error->getMessage());
        }
        $this->assertSame(0, TestAttempt::count());
    }

    public function test_the_command_dry_run_is_read_only_and_repeated_runs_do_not_add_duplicate_papers(): void
    {
        [$exam, $topic] = $this->curriculum();
        $this->questions($exam, $topic, 11);
        $this->artisan('test-series:chapters', ['--category' => ['ssc'], '--dry-run' => true])->expectsOutput('Planned 1 chapter tests; 0 already available; 0 insufficient chapter pools.')->assertSuccessful();
        $this->assertSame(0, Test::count());
        $this->artisan('test-series:chapters', ['--category' => ['ssc']])->assertSuccessful();
        $this->assertSame(10, Test::firstOrFail()->total_questions);
        $this->artisan('test-series:chapters', ['--category' => ['ssc']])->expectsOutput('Created 0 chapter tests; 1 already available; 0 insufficient chapter pools.')->assertSuccessful();
        $this->assertSame(1, Test::count());
    }

    public function test_unscoped_or_unknown_generation_requests_are_rejected(): void
    {
        $this->artisan('test-series:chapters')->assertFailed();
        $this->artisan('test-series:chapters', ['--category' => ['unknown']])->assertFailed();
        $this->assertSame(0, Test::count());
    }
}
