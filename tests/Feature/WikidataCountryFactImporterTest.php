<?php

namespace Tests\Feature;

use App\Models\Question;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WikidataCountryFactImporterTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_imports_only_unambiguous_bilingual_currency_facts_and_deduplicates_reimports(): void
    {
        $this->seed();
        Http::fake([
            'https://www.wikidata.org*' => Http::response('ok', 200),
            'https://query.wikidata.org/*' => Http::response($this->response(), 200),
        ]);

        $this->artisan('mci:wikidata-country-facts --family=currency --limit=20')->assertSuccessful();
        $this->artisan('mci:wikidata-country-facts --family=currency --limit=20')->assertSuccessful();

        $questions = Question::where('source_reference', 'wikidata-country-currency')->get();
        $this->assertCount(4, $questions);
        $this->assertFalse($questions->contains(fn (Question $question) => str_contains($question->question_text, 'Ambiguous')));

        $question = $questions->first();
        $this->assertNotEmpty($question->question_text_hi);
        $this->assertStringContainsString('currency of Alpha', $question->explanation);
        $this->assertStringContainsString('अल्फा की मुद्रा', $question->explanation_hi);
        $this->assertCount(4, $question->options);
        $this->assertCount(1, $question->options->where('is_correct', true));
        $this->assertTrue($question->is_verified);
    }

    private function response(): array
    {
        $facts = [
            ['Q1', 'Alpha', 'अल्फा', 'Q101', 'Alpha Coin', 'अल्फा मुद्रा'],
            ['Q2', 'Beta', 'बीटा', 'Q102', 'Beta Coin', 'बीटा मुद्रा'],
            ['Q3', 'Gamma', 'गामा', 'Q103', 'Gamma Coin', 'गामा मुद्रा'],
            ['Q4', 'Delta', 'डेल्टा', 'Q104', 'Delta Coin', 'डेल्टा मुद्रा'],
            ['Q5', 'Ambiguous', 'अस्पष्ट', 'Q105', 'Coin One', 'मुद्रा एक'],
            ['Q5', 'Ambiguous', 'अस्पष्ट', 'Q106', 'Coin Two', 'मुद्रा दो'],
        ];

        return ['results' => ['bindings' => array_map(fn (array $fact) => [
            'country' => ['value' => 'https://www.wikidata.org/entity/'.$fact[0]],
            'countryLabelEn' => ['value' => $fact[1]],
            'countryLabelHi' => ['value' => $fact[2]],
            'answer' => ['value' => 'https://www.wikidata.org/entity/'.$fact[3]],
            'answerLabelEn' => ['value' => $fact[4]],
            'answerLabelHi' => ['value' => $fact[5]],
        ], $facts)]];
    }
}
