<?php

namespace Tests\Feature\Services;

use App\Models\ContentSource;
use App\Models\CurrentAffairItem;
use App\Models\Exam;
use App\Models\Question;
use App\Models\QuestionImportBatch;
use App\Models\Subject;
use App\Models\Test;
use App\Models\Topic;
use App\Services\CurrentAffairsQuestionService;
use App\Services\CurrentAffairsTopicMappingService;
use App\Services\TestCatalogService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class CurrentAffairsTopicMappingTest extends TestCase
{
    use RefreshDatabase;

    private function question(array $attributes = []): Question
    {
        return Question::create(array_merge([
            'subject_id' => Subject::where('name', 'Current Affairs')->firstOrFail()->id,
            'content_source_id' => ContentSource::where('slug', 'reserve-bank-of-india')->firstOrFail()->id,
            'source_reference' => 'reserve-bank-of-india',
            'question_text' => 'RBI banking release '.str()->uuid(),
            'content_hash' => hash('sha256', (string) str()->uuid()),
            'generation_method' => 'automated', 'verification_status' => 'verified',
            'is_current_affairs' => true, 'is_active' => true, 'is_published' => true,
        ], $attributes));
    }

    public function test_backfill_is_scoped_preserves_content_and_exposes_the_chapter_in_the_catalog(): void
    {
        $this->seed(DatabaseSeeder::class);
        $question = $this->question();
        $before = $question->refresh()->getRawOriginal();
        $subject = $question->subject;
        $otherTopic = $subject->topics()->where('slug', 'national-current-affairs')->firstOrFail();
        $excluded = collect([
            $this->question(['topic_id' => $otherTopic->id]),
            $this->question(['content_source_id' => ContentSource::where('slug', 'press-information-bureau')->firstOrFail()->id]),
            $this->question(['subject_id' => Subject::where('name', 'General Knowledge')->firstOrFail()->id]),
            $this->question(['source_reference' => 'unreviewed-reference']),
            $this->question(['generation_method' => 'manual']),
            $this->question(['verification_status' => 'pending']),
            $this->question(['is_current_affairs' => false]),
            $this->question(['is_active' => false]),
            $this->question(['is_published' => false]),
        ]);
        $excludedBefore = $excluded->mapWithKeys(fn (Question $q): array => [$q->id => $q->fresh()->getRawOriginal()])->all();
        $exam = Exam::where('name', 'SSC CGL')->firstOrFail();
        $paper = Test::create(['exam_id' => $exam->id, 'title' => 'RBI practice', 'test_type' => 'topic',
            'subject_id' => $subject->id, 'topic_id' => $subject->topics()->where('slug', 'economy-and-banking')->firstOrFail()->id, 'total_questions' => 1]);
        $paper->questions()->attach($question);
        $backups = [];

        $count = app(CurrentAffairsTopicMappingService::class)->mapExistingRbiQuestions(
            function (array $originals, int $topicId) use (&$backups): void {
                $backups = ['originals' => $originals, 'topic_id' => $topicId];
            },
            function (Topic $topic, int $count) use ($exam, $subject, $paper): void {
                $catalog = app(TestCatalogService::class)->browse(Test::where('is_active', true), [
                    'exam' => $exam->id, 'subject' => $subject->id, 'topic' => $topic->id,
                ]);
                $this->assertSame(1, $count);
                $this->assertSame(['Economy and Banking'], $catalog['topics']->pluck('name')->all());
                $this->assertSame([$paper->id], $catalog['tests']->pluck('id')->all());
            },
        );

        $this->assertSame(1, $count);
        $this->assertSame([$question->id => null], $backups['originals']);
        $this->assertSame(array_merge($before, ['topic_id' => $backups['topic_id']]), $question->fresh()->getRawOriginal());
        $this->assertSame($excludedBefore, $excluded->mapWithKeys(fn (Question $q): array => [$q->id => $q->fresh()->getRawOriginal()])->all());
        $this->assertSame(0, app(CurrentAffairsTopicMappingService::class)->mapExistingRbiQuestions(
            function (array $originals): void {
                $this->assertSame([], $originals);
            },
            function (Topic $topic, int $count): void {
                $this->assertSame(0, $count);
            },
        ));
    }

    public function test_failed_live_verification_rolls_back_every_topic_change(): void
    {
        $this->seed(DatabaseSeeder::class);
        $first = $this->question();
        $second = $this->question();
        try {
            app(CurrentAffairsTopicMappingService::class)->mapExistingRbiQuestions(
                function (array $originals) use ($first, $second): void {
                    $this->assertSame([$first->id => null, $second->id => null], $originals);
                },
                function (): void {
                    throw new RuntimeException('Catalog check failed.');
                },
            );
            $this->fail('The failed verification must abort mapping.');
        } catch (RuntimeException $error) {
            $this->assertSame('Catalog check failed.', $error->getMessage());
        }
        $this->assertNull($first->fresh()->topic_id);
        $this->assertNull($second->fresh()->topic_id);
    }

    public function test_failed_backup_prevents_any_topic_change(): void
    {
        $this->seed(DatabaseSeeder::class);
        $question = $this->question();
        try {
            app(CurrentAffairsTopicMappingService::class)->mapExistingRbiQuestions(
                function (): void {
                    throw new RuntimeException('Backup unavailable.');
                },
                function (): void {
                    $this->fail('Verification must not run without a backup.');
                },
            );
            $this->fail('The failed backup must abort mapping.');
        } catch (RuntimeException $error) {
            $this->assertSame('Backup unavailable.', $error->getMessage());
        }
        $this->assertNull($question->fresh()->topic_id);
    }

    public function test_inactive_banking_topic_stops_generation_before_the_item_is_processed(): void
    {
        $this->seed(DatabaseSeeder::class);
        $source = ContentSource::where('slug', 'reserve-bank-of-india')->firstOrFail();
        $subject = Subject::where('name', 'Current Affairs')->firstOrFail();
        $subject->topics()->where('slug', 'economy-and-banking')->update(['is_active' => false]);
        $item = CurrentAffairItem::create([
            'content_source_id' => $source->id, 'title' => 'RBI Treasury Bills release',
            'content_hash' => hash('sha256', 'RBI Treasury Bills release'),
            'status' => 'approved', 'source_url' => 'https://rbi.org.in/release/qa', 'published_at' => now(),
        ]);
        $questionsBefore = Question::count();
        $batchesBefore = QuestionImportBatch::count();
        try {
            app(CurrentAffairsQuestionService::class)->createQuestion($item, ['question_text' => 'RBI Treasury Bills question', 'options' => []]);
            $this->fail('Generation must stop when the banking topic is unavailable.');
        } catch (ModelNotFoundException) {
            $this->assertSame($questionsBefore, Question::count());
            $this->assertSame($batchesBefore, QuestionImportBatch::count());
            $this->assertSame('approved', $item->fresh()->status);
            $this->assertFalse((bool) $item->fresh()->question_generated);
        }
    }

    public function test_other_sources_are_not_assigned_a_banking_topic(): void
    {
        $this->seed(DatabaseSeeder::class);
        $subject = Subject::where('name', 'Current Affairs')->firstOrFail();
        $source = ContentSource::where('slug', 'press-information-bureau')->firstOrFail();
        $service = app(CurrentAffairsTopicMappingService::class);
        $this->assertNull($service->topicForSource($subject, $source));
        $this->assertNull($service->topicForSource($subject, null));

    }

    public function test_rbi_mapping_rejects_a_subject_other_than_current_affairs(): void
    {
        $this->seed(DatabaseSeeder::class);
        $subject = Subject::where('name', 'General Knowledge')->firstOrFail();
        $source = ContentSource::where('slug', 'reserve-bank-of-india')->firstOrFail();
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('RBI current affairs require the Current Affairs subject.');
        app(CurrentAffairsTopicMappingService::class)->topicForSource($subject, $source);
    }
}
