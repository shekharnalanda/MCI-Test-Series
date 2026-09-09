<?php

namespace Tests\Feature;

use App\Models\Question;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WikidataAirportCountryImporterTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_imports_only_single_country_and_iata_bilingual_airports_idempotently(): void
    {
        $this->seed();
        Http::fake(['https://www.wikidata.org/*' => Http::response('ok', 200),
            'https://query.wikidata.org/*' => Http::response($this->response(), 200)]);
        $this->artisan('mci:wikidata-airport-countries --limit=20')->assertSuccessful();
        $this->artisan('mci:wikidata-airport-countries --limit=20')->assertSuccessful();
        $questions = Question::where('source_reference', 'wikidata-airport-country')->get();
        $this->assertCount(4, $questions);
        $this->assertFalse($questions->contains(fn (Question $question) => str_contains($question->question_text, 'Ambiguous')));
        $this->assertStringContainsString('(AAA)', $questions->first()->question_text);
        $this->assertCount(4, $questions->first()->options);
        $this->assertCount(1, $questions->first()->options->where('is_correct', true));
        $this->assertTrue($questions->first()->is_verified);
    }

    private function response(): array
    {
        $facts = [['Q1', 'Airport One', 'हवाई अड्डा एक', 'AAA', 'Q101', 'India', 'भारत'],
            ['Q2', 'Airport Two', 'हवाई अड्डा दो', 'BBB', 'Q102', 'Nepal', 'नेपाल'],
            ['Q3', 'Airport Three', 'हवाई अड्डा तीन', 'CCC', 'Q103', 'Bhutan', 'भूटान'],
            ['Q4', 'Airport Four', 'हवाई अड्डा चार', 'DDD', 'Q104', 'Sri Lanka', 'श्रीलंका'],
            ['Q5', 'Ambiguous Airport', 'अस्पष्ट हवाई अड्डा', 'EEE', 'Q105', 'France', 'फ्रांस'],
            ['Q5', 'Ambiguous Airport', 'अस्पष्ट हवाई अड्डा', 'EEE', 'Q106', 'Spain', 'स्पेन']];

        return ['results' => ['bindings' => array_map(fn (array $fact) => [
            'airport' => ['value' => 'https://www.wikidata.org/entity/'.$fact[0]],
            'airportLabelEn' => ['value' => $fact[1]], 'airportLabelHi' => ['value' => $fact[2]],
            'iata' => ['value' => $fact[3]], 'country' => ['value' => 'https://www.wikidata.org/entity/'.$fact[4]],
            'countryLabelEn' => ['value' => $fact[5]], 'countryLabelHi' => ['value' => $fact[6]],
        ], $facts)]];
    }
}
