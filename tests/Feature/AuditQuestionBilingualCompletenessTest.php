<?php

namespace Tests\Feature;

use App\Models\ContentSource;
use App\Models\Exam;
use App\Models\Subject;
use App\Services\QuestionIngestionService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AuditQuestionBilingualCompletenessTest extends TestCase
{
    use RefreshDatabase;

    public function test_strict_audit_passes_then_detects_missing_hindi_option(): void
    {
        $this->seed(DatabaseSeeder::class);

        $source = ContentSource::where('slug', 'mci-internal-verified')->firstOrFail();
        $subject = Subject::where('name', 'General Knowledge')->firstOrFail();
        $exam = Exam::where('name', 'General Competitive Examination')->firstOrFail();

        $item = [
            'exam_id' => $exam->id,
            'subject_id' => $subject->id,
            'question_text' => 'Which planet is known as the Red Planet?',
            'question_text_hi' => 'Lal grah kise kaha jata hai?',
            'explanation' => 'Mars appears red because of iron oxide.',
            'explanation_hi' => 'Mangal iron oxide ke karan lal dikhta hai.',
            'difficulty' => 'easy',
            'language' => 'bilingual',
            'options' => [
                ['option_text' => 'Mars', 'option_text_hi' => 'Mangal', 'is_correct' => true],
                ['option_text' => 'Venus', 'option_text_hi' => 'Shukra', 'is_correct' => false],
                ['option_text' => 'Jupiter', 'option_text_hi' => 'Brihaspati', 'is_correct' => false],
                ['option_text' => 'Mercury', 'option_text_hi' => 'Budh', 'is_correct' => false],
            ],
        ];

        app(QuestionIngestionService::class)->ingest([$item], $source, 'manual');

        $this->artisan('question-bank:audit-bilingual --strict')
            ->expectsOutput('Published question bank is bilingual-complete.')
            ->assertSuccessful();

        DB::table('question_options')->orderBy('id')->limit(1)->update([
            'option_text_hi' => null,
        ]);

        $this->artisan('question-bank:audit-bilingual --strict')
            ->expectsOutput('Published question bank has incomplete bilingual content.')
            ->assertFailed();
    }

    public function test_empty_published_bank_is_successful(): void
    {
        $this->artisan('question-bank:audit-bilingual --strict')
            ->expectsOutput('No published questions found for bilingual audit.')
            ->assertSuccessful();
    }
}
