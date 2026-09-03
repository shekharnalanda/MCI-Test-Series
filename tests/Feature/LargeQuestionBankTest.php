<?php

namespace Tests\Feature;

use App\Models\ContentSource;
use App\Models\Exam;
use App\Models\Question;
use App\Models\QuestionGenerationJob;
use App\Models\Subject;
use App\Models\Topic;
use App\Services\BulkQuestionImportService;
use App\Services\QuestionBankPlanner;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LargeQuestionBankTest extends TestCase
{
    use RefreshDatabase;

    public function test_topic_taxonomy_is_seeded(): void
    {
        $this->seed(
            DatabaseSeeder::class
        );

        $this->assertGreaterThan(
            100,
            Topic::count()
        );

        $subject = Subject::where(
            'name',
            'Reasoning'
        )->firstOrFail();

        $this->assertTrue(
            Topic::where(
                'subject_id',
                $subject->id
            )
                ->where(
                    'name',
                    'Coding Decoding'
                )
                ->exists()
        );
    }

    public function test_question_bank_planner_creates_reusable_generation_targets(): void
    {
        $this->seed(
            DatabaseSeeder::class
        );

        $planner = app(
            QuestionBankPlanner::class
        );

        $created =
            $planner->buildJobs(10);

        $this->assertGreaterThan(
            0,
            $created
        );

        $this->assertGreaterThan(
            0,
            QuestionGenerationJob::where(
                'status',
                'pending'
            )->count()
        );

        $job = QuestionGenerationJob::firstOrFail();

        $this->assertTrue(
            (bool) data_get(
                $job->generation_rules,
                'multi_exam_reuse'
            )
        );

        $this->assertTrue(
            (bool) data_get(
                $job->generation_rules,
                'duplicate_control'
            )
        );
    }

    public function test_bulk_json_import_uses_existing_quality_and_duplicate_pipeline(): void
    {
        $this->seed(
            DatabaseSeeder::class
        );

        $source = ContentSource::where(
            'slug',
            'mci-internal-verified'
        )->firstOrFail();

        $subject = Subject::where(
            'name',
            'General Knowledge'
        )->firstOrFail();

        $exam = Exam::where(
            'name',
            'General Competitive Examination'
        )->firstOrFail();

        $question = [
            'question_text' =>
                'Which country has New Delhi as its capital?',

            'question_text_hi' =>
                'नई दिल्ली किस देश की राजधानी है?',

            'explanation' =>
                'New Delhi is the capital of India.',

            'subject_id' =>
                $subject->id,

            'exam_ids' => [
                $exam->id
            ],

            'difficulty' =>
                'easy',

            'language' =>
                'bilingual',

            'options' => [
                [
                    'option_text' => 'India',
                    'option_text_hi' => 'भारत',
                    'is_correct' => true,
                ],
                [
                    'option_text' => 'Nepal',
                    'option_text_hi' => 'नेपाल',
                    'is_correct' => false,
                ],
                [
                    'option_text' => 'Bhutan',
                    'option_text_hi' => 'भूटान',
                    'is_correct' => false,
                ],
                [
                    'option_text' => 'Sri Lanka',
                    'option_text_hi' => 'श्रीलंका',
                    'is_correct' => false,
                ],
            ],
        ];

        $path =
            storage_path(
                'app/question-bank-test.json'
            );

        file_put_contents(
            $path,
            json_encode(
                [
                    $question,
                    $question
                ],
                JSON_UNESCAPED_UNICODE
                | JSON_PRETTY_PRINT
            )
        );

        try {
            $result = app(
                BulkQuestionImportService::class
            )->importJsonFile(
                $path,
                $source,
                1
            );

            $this->assertEquals(
                2,
                $result['received']
            );

            $this->assertEquals(
                1,
                $result['accepted']
            );

            $this->assertEquals(
                1,
                $result['duplicates']
            );

            $this->assertTrue(
                Question::where(
                    'question_text',
                    $question[
                        'question_text'
                    ]
                )
                    ->where(
                        'is_published',
                        false
                    )
                    ->exists()
            );

        } finally {
            @unlink($path);
        }
    }
}
