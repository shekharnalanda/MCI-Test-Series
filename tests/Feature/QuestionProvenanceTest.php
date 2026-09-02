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

class QuestionProvenanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_ingestion_persists_external_source_provenance_and_audit_detects_gaps(): void
    {
        $this->seed(DatabaseSeeder::class);

        $source = ContentSource::query()->where('slug', 'press-information-bureau')->firstOrFail();
        $subject = Subject::query()->where('name', 'General Knowledge')->firstOrFail();
        $exam = Exam::query()->where('name', 'General Competitive Examination')->firstOrFail();

        $item = [
            'question_text' => 'Which institution issued this verified sample update?',
            'question_text_hi' => 'यह सत्यापित नमूना अपडेट किस संस्था ने जारी किया?',
            'explanation' => 'The Press Information Bureau issued the sample update.',
            'subject_id' => $subject->id,
            'exam_ids' => [$exam->id],
            'difficulty' => 'easy',
            'language' => 'bilingual',
            'source_url' => 'https://pib.gov.in/sample-release',
            'source_reference' => 'PIB-SAMPLE-001',
            'source_published_at' => '2026-09-01 10:00:00',
            'options' => [
                ['option_text' => 'Press Information Bureau', 'is_correct' => true],
                ['option_text' => 'Reserve Bank of India', 'is_correct' => false],
                ['option_text' => 'National Testing Agency', 'is_correct' => false],
                ['option_text' => 'Staff Selection Commission', 'is_correct' => false],
            ],
        ];

        app(QuestionIngestionService::class)->ingest([$item], $source, 'manual');

        $question = Question::query()->where('source_reference', 'PIB-SAMPLE-001')->firstOrFail();

        $this->assertSame('https://pib.gov.in/sample-release', $question->source_url);
        $this->assertSame('PIB-SAMPLE-001', $question->source_reference);
        $this->assertNotNull($question->source_published_at);
        $this->assertNotNull($question->imported_at);

        $this->artisan('question-bank:audit-provenance', ['--strict' => true])
            ->expectsOutputToContain('Question provenance audit passed.')
            ->assertSuccessful();

        $question->forceFill(['source_url' => null])->save();

        $this->artisan('question-bank:audit-provenance', ['--strict' => true])
            ->expectsOutputToContain('Question provenance audit failed.')
            ->assertFailed();
    }
}
