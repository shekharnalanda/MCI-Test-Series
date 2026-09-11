<?php

namespace Tests\Feature;

use App\Models\QuestionGenerationJob;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExportQuestionGenerationBriefsTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_exports_prioritized_read_only_hard_question_briefs(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->artisan('mci:question-bank-plan --target=500')->assertSuccessful();

        $before = QuestionGenerationJob::query()->get()->map(
            fn (QuestionGenerationJob $job) => $job->only([
                'id', 'status', 'generated_count', 'accepted_count', 'updated_at',
            ])
        )->toArray();

        $this->artisan('question-bank:export-briefs --difficulty=hard --limit=2')
            ->expectsOutputToContain('"read_only": true')
            ->expectsOutputToContain('"difficulty": "hard"')
            ->expectsOutputToContain('"content_requirements"')
            ->expectsOutputToContain('"eligible_topics"')
            ->assertSuccessful();

        $after = QuestionGenerationJob::query()->get()->map(
            fn (QuestionGenerationJob $job) => $job->only([
                'id', 'status', 'generated_count', 'accepted_count', 'updated_at',
            ])
        )->toArray();

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
