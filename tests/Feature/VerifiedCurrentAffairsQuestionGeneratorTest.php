<?php

namespace Tests\Feature;

use App\Models\ContentSource;
use App\Models\CurrentAffairItem;
use App\Models\Question;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VerifiedCurrentAffairsQuestionGeneratorTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_generates_only_source_backed_bilingual_penalty_questions(): void
    {
        $this->seed(DatabaseSeeder::class);
        $source = ContentSource::where('slug', 'reserve-bank-of-india')->firstOrFail();

        foreach ([
            ['Alpha Credit Limited', '₹1,19,400'],
            ['Beta Finserve Limited', '₹4.20 lakh'],
            ['Gamma Information Services Limited', '₹6,89,600'],
            ['Delta CIBIL Limited', '₹26,82,800'],
        ] as $index => [$entity, $amount]) {
            CurrentAffairItem::create([
                'content_source_id' => $source->id,
                'title' => "RBI imposes monetary penalty on {$entity}",
                'summary' => "The Reserve Bank of India (RBI) has, by an order dated August 31, 2026, imposed a monetary penalty of {$amount} on {$entity} for non-compliance with specified directions.",
                'source_url' => "https://rbi.org.in/release/{$index}",
                'published_at' => now(),
                'fetched_at' => now(),
                'content_hash' => hash('sha256', $entity),
                'trust_score' => 100,
                'freshness_score' => 100,
                'quality_score' => 100,
                'status' => 'approved',
                'auto_approved' => true,
            ]);
        }

        $this->artisan('mci:current-affairs-generate-verified')->assertSuccessful();

        $this->assertSame(4, Question::where('is_current_affairs', true)->count());
        $question = Question::where('is_current_affairs', true)->latest('id')->firstOrFail();
        $this->assertSame('bilingual', $question->language);
        $this->assertNotEmpty($question->question_text_hi);
        $this->assertSame('verified', $question->verification_status);
        $this->assertCount(4, $question->options);
        $this->assertSame(1, $question->options->where('is_correct', true)->count());
    }
}
