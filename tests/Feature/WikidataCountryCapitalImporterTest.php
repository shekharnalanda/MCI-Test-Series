<?php

namespace Tests\Feature;

use App\Models\Question;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WikidataCountryCapitalImporterTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_imports_only_complete_bilingual_cc0_facts_and_blocks_duplicates(): void
    {
        $this->seed(DatabaseSeeder::class);
        Http::fake([
            'https://www.wikidata.org*' => Http::response('ok', 200),
            'https://query.wikidata.org/*' => Http::response($this->response(), 200),
        ]);

        $this->artisan('mci:wikidata-capitals --limit=10')->assertSuccessful();
        $this->artisan('mci:wikidata-capitals --limit=10')->assertSuccessful();

        $this->assertSame(4, Question::where('source_reference', 'wikidata-country-capital')->count());
        $question = Question::where('source_reference', 'wikidata-country-capital')->firstOrFail();
        $this->assertSame('bilingual', $question->language);
        $this->assertSame('verified', $question->verification_status);
        $this->assertTrue($question->is_published);
        $this->assertNotEmpty($question->question_text_hi);
        $this->assertCount(4, $question->options);
        $this->assertSame(1, $question->options->where('is_correct', true)->count());
    }

    private function response(): array
    {
        $facts = [
            ['Q668', 'India', 'भारत', 'Q987', 'New Delhi', 'नई दिल्ली'],
            ['Q142', 'France', 'फ़्रांस', 'Q90', 'Paris', 'पेरिस'],
            ['Q183', 'Germany', 'जर्मनी', 'Q64', 'Berlin', 'बर्लिन'],
            ['Q408', 'Australia', 'ऑस्ट्रेलिया', 'Q61', 'Canberra', 'कैनबरा'],
        ];

        return ['results' => ['bindings' => collect($facts)->map(fn (array $fact) => [
            'country' => ['value' => 'https://www.wikidata.org/entity/'.$fact[0]],
            'countryLabelEn' => ['value' => $fact[1]],
            'countryLabelHi' => ['value' => $fact[2]],
            'capital' => ['value' => 'https://www.wikidata.org/entity/'.$fact[3]],
            'capitalLabelEn' => ['value' => $fact[4]],
            'capitalLabelHi' => ['value' => $fact[5]],
        ])->all()]];
    }
}
