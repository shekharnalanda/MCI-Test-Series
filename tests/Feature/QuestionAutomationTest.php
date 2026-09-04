<?php

namespace Tests\Feature;

use App\Models\ContentSource;
use App\Models\Exam;
use App\Models\Question;
use App\Models\Subject;
use App\Services\AutomaticTestGenerator;
use App\Services\QuestionIngestionService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuestionAutomationTest extends TestCase
{
    use RefreshDatabase;

    public function test_ingestion_detects_duplicates_and_auto_publishes_trusted_content(): void
    {
        $this->seed(DatabaseSeeder::class);

        $source = ContentSource::where(
            'slug',
            'press-information-bureau'
        )->firstOrFail();

        $subject = Subject::where(
            'name',
            'General Knowledge'
        )->firstOrFail();

        $exam = Exam::where(
            'name',
            'General Competitive Examination'
        )->firstOrFail();

        $item = [
            'question_text' =>
                'Which ocean is the largest ocean on Earth?',

            'question_text_hi' =>
                'पृथ्वी का सबसे बड़ा महासागर कौन सा है?',

            'explanation' =>
                'The Pacific Ocean is the largest ocean on Earth.',

            'subject_id' => $subject->id,
            'exam_ids' => [$exam->id],
            'difficulty' => 'easy',
            'language' => 'bilingual',
            'source_url' => 'https://example.test/oceans', 'source_reference' => 'trusted-ocean-reference', 'source_published_at' => now()->toDateString(),

            'options' => [
                [
                    'option_text' => 'Pacific Ocean',
                    'option_text_hi' => 'प्रशांत महासागर',
                    'is_correct' => true,
                ],
                [
                    'option_text' => 'Atlantic Ocean',
                    'option_text_hi' => 'अटलांटिक महासागर',
                    'is_correct' => false,
                ],
                [
                    'option_text' => 'Indian Ocean',
                    'option_text_hi' => 'हिन्द महासागर',
                    'is_correct' => false,
                ],
                [
                    'option_text' => 'Arctic Ocean',
                    'option_text_hi' => 'आर्कटिक महासागर',
                    'is_correct' => false,
                ],
            ],
        ];

        $service = app(QuestionIngestionService::class);

        $first = $service->ingest(
            [$item],
            $source,
            'generated'
        );

        $this->assertEquals(1, $first->accepted_count);
        $this->assertEquals(0, $first->duplicate_count);

        $question = Question::where(
            'question_text',
            $item['question_text']
        )->firstOrFail();

        $this->assertTrue($question->is_published);
        $this->assertEquals(
            'verified',
            $question->verification_status
        );

        /*
         * Same question with punctuation/case/spacing changes
         * must still be treated as duplicate.
         */
        $duplicate = $item;

        $duplicate['question_text'] =
            '  WHICH ocean is the largest ocean on Earth???  ';

        $second = $service->ingest(
            [$duplicate],
            $source,
            'generated'
        );

        $this->assertEquals(0, $second->accepted_count);
        $this->assertEquals(1, $second->duplicate_count);
    }

    public function test_automatic_generator_builds_test_from_verified_questions(): void
    {
        $this->seed(DatabaseSeeder::class);

        $exam = Exam::where(
            'name',
            'General Competitive Examination'
        )->firstOrFail();

        /*
         * Demo seeder already provides 5 verified/published
         * questions mapped to this exam.
         */
        $generator = app(AutomaticTestGenerator::class);

        $test = $generator->generate(
            $exam,
            5,
            'mixed',
            'practice'
        );

        $this->assertTrue($test->auto_generated);
        $this->assertEquals(5, $test->questions()->count());
        $this->assertEquals(
            'least_used_randomized',
            data_get($test->generation_rules, 'selection')
        );
    }
}
