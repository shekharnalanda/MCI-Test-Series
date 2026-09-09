<?php

namespace Tests\Feature;

use App\Models\Question;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WikidataStadiumCountryImporterTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_imports_only_single_country_bilingual_stadiums_idempotently(): void
    {
        $this->seed();
        Http::fake(['https://www.wikidata.org/*' => Http::response('ok', 200),
            'https://query.wikidata.org/*' => Http::response($this->response(), 200)]);
        $this->artisan('mci:wikidata-stadium-countries --limit=20')->assertSuccessful();
        $this->artisan('mci:wikidata-stadium-countries --limit=20')->assertSuccessful();
        $questions = Question::where('source_reference', 'wikidata-stadium-country')->get();
        $this->assertCount(4, $questions);
        $this->assertFalse($questions->contains(fn (Question $question) => str_contains($question->question_text, 'Ambiguous')));
        $this->assertCount(4, $questions->first()->options);
        $this->assertCount(1, $questions->first()->options->where('is_correct', true));
        $this->assertTrue($questions->first()->is_verified);
    }

    private function response(): array
    {
        $facts = [['Q1', 'Stadium One', 'स्टेडियम एक', 'Q101', 'India', 'भारत'],
            ['Q2', 'Stadium Two', 'स्टेडियम दो', 'Q102', 'Nepal', 'नेपाल'],
            ['Q3', 'Stadium Three', 'स्टेडियम तीन', 'Q103', 'Bhutan', 'भूटान'],
            ['Q4', 'Stadium Four', 'स्टेडियम चार', 'Q104', 'Sri Lanka', 'श्रीलंका'],
            ['Q5', 'Ambiguous Stadium', 'अस्पष्ट स्टेडियम', 'Q105', 'France', 'फ्रांस'],
            ['Q5', 'Ambiguous Stadium', 'अस्पष्ट स्टेडियम', 'Q106', 'Spain', 'स्पेन']];

        return ['results' => ['bindings' => array_map(fn (array $fact) => [
            'stadium' => ['value' => 'https://www.wikidata.org/entity/'.$fact[0]],
            'stadiumLabelEn' => ['value' => $fact[1]], 'stadiumLabelHi' => ['value' => $fact[2]],
            'country' => ['value' => 'https://www.wikidata.org/entity/'.$fact[3]],
            'countryLabelEn' => ['value' => $fact[4]], 'countryLabelHi' => ['value' => $fact[5]],
        ], $facts)]];
    }
}
