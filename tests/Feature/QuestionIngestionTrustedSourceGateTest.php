<?php

namespace Tests\Feature;

use App\Models\ContentSource;
use App\Models\Exam;
use App\Models\Question;
use App\Models\Subject;
use App\Models\Topic;
use App\Services\QuestionIngestionService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuestionIngestionTrustedSourceGateTest extends TestCase
{
    use RefreshDatabase;

    public function test_quarantined_source_cannot_auto_publish_question(): void
    {
        $this->seed(DatabaseSeeder::class);

        $source = ContentSource::query()
            ->where('slug', 'mci-internal-verified')
            ->firstOrFail();

        $source->update([
            'allow_question_generation' => true,
            'auto_publish_allowed' => true,
            'trust_score' => 100,
            'is_quarantined' => true,
            'quarantined_at' => now(),
        ]);

        $subject = Subject::query()->firstOrFail();
        $exam = Exam::query()->firstOrFail();

        $batch = app(QuestionIngestionService::class)->ingest(
            [[
                'question_text' => 'Which planet is known as the Red Planet?',
                'explanation' => 'Mars appears red because iron minerals in its soil oxidize.',
                'subject_id' => $subject->id,
                'exam_ids' => [$exam->id],
                'difficulty' => 'easy',
                'language' => 'english',
                'options' => [
                    ['option_text' => 'Mars', 'is_correct' => true],
                    ['option_text' => 'Venus', 'is_correct' => false],
                    ['option_text' => 'Jupiter', 'is_correct' => false],
                    ['option_text' => 'Mercury', 'is_correct' => false],
                ],
            ]],
            $source->fresh(),
            'generated',
        );

        $question = Question::query()->latest('id')->firstOrFail();

        $this->assertSame(1, $batch->accepted_count);
        $this->assertFalse($question->is_published);
        $this->assertSame('pending', $question->verification_status);
    }

    public function test_incomplete_hard_question_is_rejected(): void
    {
        $this->seed(DatabaseSeeder::class);

        $source = ContentSource::where(
            'slug',
            'press-information-bureau'
        )->firstOrFail();
        $subject = Subject::where('name', 'General Knowledge')->firstOrFail();
        $exam = Exam::where(
            'name',
            'General Competitive Examination'
        )->firstOrFail();

        $batch = app(QuestionIngestionService::class)->ingest([[
            'question_text' => 'Which policy conclusion follows from the facts?',
            'subject_id' => $subject->id,
            'exam_ids' => [$exam->id],
            'difficulty' => 'hard',
            'language' => 'bilingual',
            'source_url' => 'https://pib.gov.in/policy-sample',
            'source_reference' => 'PIB-HARD-INCOMPLETE',
            'source_published_at' => now()->toDateString(),
            'options' => [
                ['option_text' => 'A', 'is_correct' => true],
                ['option_text' => 'B', 'is_correct' => false],
                ['option_text' => 'C', 'is_correct' => false],
                ['option_text' => 'D', 'is_correct' => false],
            ],
        ]], $source, 'generated');

        $this->assertSame(0, $batch->accepted_count);
        $this->assertSame(1, $batch->rejected_count);
    }

    public function test_complete_aligned_hard_question_can_auto_publish(): void
    {
        $this->seed(DatabaseSeeder::class);

        $source = ContentSource::where(
            'slug',
            'press-information-bureau'
        )->firstOrFail();
        $subject = Subject::where('name', 'General Knowledge')->firstOrFail();
        $topic = Topic::where('subject_id', $subject->id)
            ->where('is_active', true)
            ->firstOrFail();
        $exam = Exam::where(
            'name',
            'General Competitive Examination'
        )->firstOrFail();

        $batch = app(QuestionIngestionService::class)->ingest([[
            'question_text' => 'Which conclusion best combines both official facts?',
            'question_text_hi' => 'कौन-सा निष्कर्ष दोनों आधिकारिक तथ्यों को सही जोड़ता है?',
            'explanation' => 'The correct option follows from both stated facts.',
            'explanation_hi' => 'सही विकल्प दोनों दिए गए तथ्यों से निष्कर्षित होता है।',
            'subject_id' => $subject->id,
            'topic_id' => $topic->id,
            'exam_ids' => [$exam->id],
            'difficulty' => 'hard',
            'language' => 'bilingual',
            'source_url' => 'https://pib.gov.in/hard-quality-sample',
            'source_reference' => 'PIB-HARD-COMPLETE',
            'source_published_at' => now()->toDateString(),
            'options' => [
                ['option_text' => 'First conclusion', 'option_text_hi' => 'पहला निष्कर्ष', 'is_correct' => true],
                ['option_text' => 'Second conclusion', 'option_text_hi' => 'दूसरा निष्कर्ष', 'is_correct' => false],
                ['option_text' => 'Third conclusion', 'option_text_hi' => 'तीसरा निष्कर्ष', 'is_correct' => false],
                ['option_text' => 'Fourth conclusion', 'option_text_hi' => 'चौथा निष्कर्ष', 'is_correct' => false],
            ],
        ]], $source, 'generated');

        $question = Question::where(
            'source_reference',
            'PIB-HARD-COMPLETE'
        )->firstOrFail();

        $this->assertSame(1, $batch->accepted_count);
        $this->assertTrue($question->is_published);
        $this->assertSame('verified', $question->verification_status);

        $this->artisan('question-bank:audit-hard-quality --strict')
            ->expectsOutputToContain('Hard-question quality audit passed.')
            ->assertSuccessful();
    }
}
