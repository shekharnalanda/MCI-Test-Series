<?php

namespace Tests\Feature;

use App\Models\ContentSource;
use App\Models\CurrentAffairItem;
use App\Models\Exam;
use App\Models\Question;
use App\Services\CurrentAffairsMaintenanceService;
use App\Services\CurrentAffairsQuestionService;
use App\Services\CurrentAffairsService;
use App\Services\CurrentAffairsTestGenerator;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CurrentAffairsAutomationTest extends TestCase
{
    use RefreshDatabase;

    public function test_trusted_current_affair_can_be_ingested_auto_approved_and_deduplicated(): void
    {
        $this->seed(DatabaseSeeder::class);

        $source = ContentSource::where(
            'slug',
            'mci-internal-verified'
        )->firstOrFail();

        $service = app(CurrentAffairsService::class);

        $payload = [[
            'title' =>
                'Government announces major national education initiative for students',

            'summary' =>
                'A verified current affairs summary containing sufficient contextual information for competitive examination preparation and question generation.',

            'source_url' =>
                'https://example.test/current-affairs/1',

            'published_at' =>
                now()->toIso8601String(),
        ]];

        $first = $service->ingest(
            $source,
            $payload
        );

        $this->assertEquals(
            1,
            $first['accepted']
        );

        $this->assertEquals(
            0,
            $first['duplicates']
        );

        $item = CurrentAffairItem::firstOrFail();

        $this->assertEquals(
            'approved',
            $item->status
        );

        $this->assertTrue(
            $item->auto_approved
        );

        $this->assertGreaterThanOrEqual(
            90,
            $item->trust_score
        );

        $this->assertGreaterThanOrEqual(
            70,
            $item->freshness_score
        );

        $second = $service->ingest(
            $source,
            $payload
        );

        $this->assertEquals(
            0,
            $second['accepted']
        );

        $this->assertEquals(
            1,
            $second['duplicates']
        );
    }

    public function test_untrusted_source_does_not_auto_approve(): void
    {
        $this->seed(DatabaseSeeder::class);

        $source = ContentSource::where(
            'slug',
            'approved-open-knowledge'
        )->firstOrFail();

        $source->update([
            'allow_current_affairs' => true,
            'auto_publish_allowed' => false,
        ]);

        $service = app(CurrentAffairsService::class);

        $result = $service->ingest(
            $source,
            [[
                'title' =>
                    'Current affairs item requiring administrative review',

                'summary' =>
                    'This current affairs item contains enough information but its source is not permitted to automatically publish content.',

                'source_url' =>
                    'https://example.test/review-item',

                'published_at' =>
                    now()->toIso8601String(),
            ]]
        );

        $this->assertEquals(
            1,
            $result['accepted']
        );

        $item = CurrentAffairItem::firstOrFail();

        $this->assertEquals(
            'pending',
            $item->status
        );

        $this->assertFalse(
            $item->auto_approved
        );
    }

    public function test_approved_item_can_generate_verified_current_affairs_question(): void
    {
        $this->seed(DatabaseSeeder::class);

        $source = ContentSource::where(
            'slug',
            'mci-internal-verified'
        )->firstOrFail();

        $item = CurrentAffairItem::create([
            'content_source_id' => $source->id,

            'title' =>
                'Verified Current Affairs Event for Automation Test',

            'summary' =>
                'Verified explanatory current affairs summary used only for automated application testing.',

            'source_url' =>
                'https://example.test/event',

            'published_at' => now(),
            'fetched_at' => now(),

            'content_hash' =>
                hash(
                    'sha256',
                    'verified-ca-event'
                ),

            'trust_score' => 100,
            'freshness_score' => 100,
            'quality_score' => 100,

            'status' => 'approved',
            'auto_approved' => true,
        ]);

        $exam = Exam::where(
            'name',
            'General Competitive Examination'
        )->firstOrFail();

        $service = app(
            CurrentAffairsQuestionService::class
        );

        $service->createQuestion(
            $item,
            [
                'question_text' =>
                    'Which option represents the verified current affairs event used in this automation test?',

                'question_text_hi' =>
                    'इस ऑटोमेशन परीक्षण में प्रयुक्त सत्यापित करेंट अफेयर्स घटना को कौन सा विकल्प दर्शाता है?',

                'explanation' =>
                    'This is a controlled MCI automation test question.',

                'exam_ids' => [
                    $exam->id
                ],

                'options' => [
                    [
                        'option_text' =>
                            'Verified Event',

                        'option_text_hi' =>
                            'सत्यापित घटना',

                        'is_correct' => true,
                    ],
                    [
                        'option_text' =>
                            'Unrelated Event A',

                        'option_text_hi' =>
                            'असंबंधित घटना A',

                        'is_correct' => false,
                    ],
                    [
                        'option_text' =>
                            'Unrelated Event B',

                        'option_text_hi' =>
                            'असंबंधित घटना B',

                        'is_correct' => false,
                    ],
                    [
                        'option_text' =>
                            'Unrelated Event C',

                        'option_text_hi' =>
                            'असंबंधित घटना C',

                        'is_correct' => false,
                    ],
                ],
            ]
        );

        $item->refresh();

        $this->assertTrue(
            $item->question_generated
        );

        $this->assertEquals(
            'processed',
            $item->status
        );

        $question = Question::where(
            'is_current_affairs',
            true
        )
            ->where(
                'question_text',
                'Which option represents the verified current affairs event used in this automation test?'
            )
            ->firstOrFail();

        $this->assertTrue(
            $question->is_published
        );

        $this->assertEquals(
            'verified',
            $question->verification_status
        );

        $this->assertEquals(
            now()->toDateString(),
            $question
                ->current_affair_date
                ->toDateString()
        );

        $this->assertTrue(
            $question->exams()
                ->where(
                    'exams.id',
                    $exam->id
                )
                ->exists()
        );
    }

    public function test_old_current_affairs_items_expire(): void
    {
        $this->seed(DatabaseSeeder::class);

        $source = ContentSource::where(
            'slug',
            'mci-internal-verified'
        )->firstOrFail();

        $item = CurrentAffairItem::create([
            'content_source_id' => $source->id,

            'title' =>
                'Very Old Current Affairs Item',

            'summary' =>
                'Old item created to validate automatic expiry.',

            'published_at' =>
                now()->subDays(500),

            'fetched_at' =>
                now()->subDays(500),

            'content_hash' =>
                hash(
                    'sha256',
                    'very-old-current-affairs-item'
                ),

            'trust_score' => 100,
            'freshness_score' => 10,
            'quality_score' => 100,

            'status' => 'approved',
        ]);

        $service = app(
            CurrentAffairsMaintenanceService::class
        );

        $expired = $service->expireOldItems(
            365
        );

        $this->assertEquals(
            1,
            $expired
        );

        $this->assertEquals(
            'expired',
            $item->fresh()->status
        );
    }

    public function test_current_affairs_test_generator_builds_test_from_fresh_verified_questions(): void
    {
        $this->seed(DatabaseSeeder::class);

        $source = ContentSource::where(
            'slug',
            'mci-internal-verified'
        )->firstOrFail();

        $exam = Exam::where(
            'name',
            'General Competitive Examination'
        )->firstOrFail();

        $questionService = app(
            CurrentAffairsQuestionService::class
        );

        for ($i = 1; $i <= 5; $i++) {

            $item = CurrentAffairItem::create([
                'content_source_id' =>
                    $source->id,

                'title' =>
                    "Fresh Current Affairs Event {$i}",

                'summary' =>
                    "Verified current affairs summary {$i} for automatic test generation.",

                'source_url' =>
                    "https://example.test/fresh/{$i}",

                'published_at' =>
                    now(),

                'fetched_at' =>
                    now(),

                'content_hash' =>
                    hash(
                        'sha256',
                        "fresh-current-affairs-{$i}"
                    ),

                'trust_score' => 100,
                'freshness_score' => 100,
                'quality_score' => 100,

                'status' => 'approved',
                'auto_approved' => true,
            ]);

            $questionService->createQuestion(
                $item,
                [
                    'question_text' =>
                        "Which answer is correct for fresh current affairs automation question {$i}?",

                    'question_text_hi' =>
                        "ताज़ा करेंट अफेयर्स ऑटोमेशन प्रश्न {$i} का सही उत्तर कौन सा है?",

                    'explanation' =>
                        "Controlled current affairs explanation {$i}.",

                    'exam_ids' => [
                        $exam->id
                    ],

                    'options' => [
                        [
                            'option_text' =>
                                "Correct {$i}",

                            'is_correct' => true,
                        ],
                        [
                            'option_text' =>
                                "Wrong A {$i}",

                            'is_correct' => false,
                        ],
                        [
                            'option_text' =>
                                "Wrong B {$i}",

                            'is_correct' => false,
                        ],
                        [
                            'option_text' =>
                                "Wrong C {$i}",

                            'is_correct' => false,
                        ],
                    ],
                ]
            );
        }

        $generator = app(
            CurrentAffairsTestGenerator::class
        );

        $test = $generator->generate(
            'weekly',
            5
        );

        $this->assertTrue(
            $test->auto_generated
        );

        $this->assertEquals(
            'current_affairs',
            $test->test_type
        );

        $this->assertEquals(
            5,
            $test->questions()->count()
        );

        $this->assertEquals(
            'weekly',
            data_get(
                $test->generation_rules,
                'period'
            )
        );
    }
}
