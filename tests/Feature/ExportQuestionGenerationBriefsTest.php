<?php

namespace Tests\Feature;

use App\Models\Exam;
use App\Models\QuestionGenerationJob;
use App\Models\Subject;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExportQuestionGenerationBriefsTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_exports_prioritized_read_only_hard_question_briefs(): void
    {
        $this->seed(DatabaseSeeder::class);

        $exam = Exam::where('name', 'General Competitive Examination')->firstOrFail();
        $subject = Subject::where('name', 'General Knowledge')->firstOrFail();

        QuestionGenerationJob::create([
            'job_code' => 'QG-EXPORT-TEST',
            'exam_id' => $exam->id,
            'subject_id' => $subject->id,
            'target_count' => 10,
            'difficulty' => 'mixed',
            'language' => 'bilingual',
            'status' => 'pending',
            'priority' => 90,
            'generation_rules' => [
                'difficulty_deficits' => [
                    'easy' => 3,
                    'medium' => 5,
                    'hard' => 2,
                ],
            ],
        ]);

        $before = QuestionGenerationJob::query()
            ->get(['id', 'status', 'generated_count', 'accepted_count', 'updated_at'])
            ->toJson();

        $this->artisan('question-bank:export-briefs --difficulty=hard --limit=2')
            ->expectsOutputToContain('"read_only": true')
            ->assertSuccessful();

        $after = QuestionGenerationJob::query()
            ->get(['id', 'status', 'generated_count', 'accepted_count', 'updated_at'])
            ->toJson();

        $this->assertSame($before, $after);
    }

    public function test_it_rejects_invalid_options(): void
    {
        $this->artisan('question-bank:export-briefs --difficulty=extreme')
            ->expectsOutputToContain('Difficulty must be')
            ->assertFailed();

        $this->artisan('question-bank:export-briefs --limit=0')
             ->expectsOutputToContain('Limit must be between')
            ->assertFailed();
    }
}
