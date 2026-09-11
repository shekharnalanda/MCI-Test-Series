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
use RuntimeException;
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
            'source_url' => 'https://pib.gov.in/oceans', 'source_reference' => 'trusted-ocean-reference', 'source_published_at' => now()->toDateString(),

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
        foreach (['source_url', 'source_reference', 'source_published_at'] as $missingField) { $unverified = $item; $unverified['question_text'] = 'Which source field is missing: '.$missingField.'?'; unset($unverified[$missingField]); $batch = $service->ingest([$unverified], $source, 'generated'); $pendingQuestion = Question::where('question_text', $unverified['question_text'])->firstOrFail(); $this->assertSame(1, $batch->accepted_count); $this->assertFalse($pendingQuestion->is_published, $missingField.' should block auto publish'); $this->assertSame('pending', $pendingQuestion->verification_status); }
        foreach ([['source_url' => 'javascript:alert(1)'], ['source_published_at' => now()->addDay()->toDateString()]] as $index => $invalidFields) { $unverified = array_merge($item, $invalidFields); $unverified['question_text'] = 'Which provenance value is invalid: '.$index.'?'; $batch = $service->ingest([$unverified], $source, 'generated'); $pendingQuestion = Question::where('question_text', $unverified['question_text'])->firstOrFail(); $this->assertSame(1, $batch->accepted_count); $this->assertFalse($pendingQuestion->is_published); $this->assertSame('pending', $pendingQuestion->verification_status); }
    }

    public function test_automatic_generator_builds_test_from_verified_questions(): void
    {
        $this->seed(DatabaseSeeder::class);

        $exam = Exam::where(
            'name',
            'General Competitive Examination'
        )->firstOrFail();

        $this->seedEligibleQuestions($exam);

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
            'least_used_topic_balanced',
            data_get($test->generation_rules, 'selection')
        );
        $this->assertTrue(
            data_get($test->generation_rules, 'subject_alignment_required')
        );
        $this->assertTrue(
            data_get($test->generation_rules, 'topic_balance_required')
        );
        $this->assertSame(
            ['easy' => 30, 'medium' => 50, 'hard' => 20],
            data_get($test->generation_rules, 'difficulty_balance')
        );
        $this->assertSame(
            5,
            array_sum(
                data_get(
                    $test->generation_rules,
                    'actual_difficulty_counts'
                )
            )
        );
    }

    public function test_full_mock_uses_a_separate_series_and_correct_titles(): void
    {
        $this->seed(DatabaseSeeder::class);

        $exam = Exam::where(
            'name',
            'General Competitive Examination'
        )->firstOrFail();

        $this->seedEligibleQuestions($exam);

        $test = app(AutomaticTestGenerator::class)->generate(
            $exam,
            5,
            'mixed',
            'full_mock'
        );

        $this->assertSame('full_mock', $test->test_type);
        $this->assertStringContainsString('Full Mock Test', $test->title);
        $this->assertStringContainsString('फुल मॉक टेस्ट', $test->title_hi);
        $this->assertSame('auto-full_mock-'.$exam->slug, $test->series->slug);
        $this->assertSame('full_mock', $test->series->series_type);
    }

    public function test_mixed_generation_enforces_difficulty_diversity_gate(): void
    {
        $this->seed(DatabaseSeeder::class);

        $exam = Exam::where(
            'name',
            'General Competitive Examination'
        )->firstOrFail();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Difficulty gate:');

        app(AutomaticTestGenerator::class)->generate(
            $exam,
            5,
            'mixed',
            'practice',
            2
        );
    }
    private function seedEligibleQuestions(Exam $exam, int $count = 5): void
    {
        $source = ContentSource::where('slug', 'press-information-bureau')->firstOrFail();
        $subject = Subject::where('name', 'General Knowledge')->firstOrFail();
        $difficulties = ['easy', 'easy', 'medium', 'medium', 'hard'];

        $items = collect(range(1, $count))->map(function (int $number) use ($exam, $subject, $difficulties): array {
            return [
                'question_text' => "Verified automation fixture question {$number}?",
                'question_text_hi' => "सत्यापित स्वचालन परीक्षण प्रश्न {$number}?",
                'explanation' => "Verified explanation for fixture {$number}.",
                'subject_id' => $subject->id,
                'exam_ids' => [$exam->id],
                'difficulty' => $difficulties[($number - 1) % count($difficulties)],
                'language' => 'bilingual',
                'source_url' => "https://pib.gov.in/fixture-{$number}",
                'source_reference' => "PIB-FIXTURE-{$number}",
                'source_published_at' => now()->subDay()->toDateString(),
                'options' => [
                    ['option_text' => "Correct {$number}", 'option_text_hi' => "सही {$number}", 'is_correct' => true],
                    ['option_text' => "Wrong A {$number}", 'option_text_hi' => "गलत क {$number}", 'is_correct' => false],
                    ['option_text' => "Wrong B {$number}", 'option_text_hi' => "गलत ख {$number}", 'is_correct' => false],
                    ['option_text' => "Wrong C {$number}", 'option_text_hi' => "गलत ग {$number}", 'is_correct' => false],
                ],
            ];
        })->all();

        $batch = app(QuestionIngestionService::class)->ingest($items, $source, 'generated');

        $this->assertSame($count, $batch->accepted_count);
    }

}
