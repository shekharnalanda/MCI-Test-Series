<?php

namespace Tests\Feature;

use App\Models\ContentSource;
use App\Models\Exam;
use App\Models\Question;
use App\Models\Subject;
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
}
