<?php

namespace Tests\Feature;

use App\Models\Question;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WikidataWorldHeritageCountryImporterTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_imports_only_single_country_bilingual_sites_idempotently(): void
    {
        $this->seed();
        Http::fake(['https://www.wikidata.org/*' => Http::response('ok', 200),
            'https://query.wikidata.org/*' => Http::response($this->response(), 200)]);
        $this->artisan('mci:wikidata-world-heritage-countries --limit=20')->assertSuccessful();
        $this->artisan('mci:wikidata-world-heritage-countries --limit=20')->assertSuccessful();
        $questions = Question::where('source_reference', 'wikidata-world-heritage-country')->get();
        $this->assertCount(4, $questions);
        $this->assertFalse($questions->contains(fn (Question $question) => str_contains($question->question_text, 'Transnational')));
        $this->assertCount(4, $questions->first()->options);
        $this->assertCount(1, $questions->first()->options->where('is_correct', true));
        $this->assertTrue($questions->first()->is_verified);
    }

    private function response(): array
    {
        $facts = [['Q1', 'Site One', 'स्थल एक', 'Q101', 'India', 'भारत'],
            ['Q2', 'Site Two', 'स्थल दो', 'Q102', 'Nepal', 'नेपाल'],
            ['Q3', 'Site Three', 'स्थल तीन', 'Q103', 'Bhutan', 'भूटान'],
            ['Q4', 'Site Four', 'स्थल चार', 'Q104', 'Sri Lanka', 'श्रीलंका'],
            ['Q5', 'Transnational Site', 'अंतरराष्ट्रीय स्थल', 'Q105', 'France', 'फ्रांस'],
            ['Q5', 'Transnational Site', 'अंतरराष्ट्रीय स्थल', 'Q106', 'Spain', 'स्पेन']];

        return ['results' => ['bindings' => array_map(fn (array $fact) => [
            'site' => ['value' => 'https://www.wikidata.org/entity/'.$fact[0]],
            'siteLabelEn' => ['value' => $fact[1]], 'siteLabelHi' => ['value' => $fact[2]],
            'country' => ['value' => 'https://www.wikidata.org/entity/'.$fact[3]],
            'countryLabelEn' => ['value' => $fact[4]], 'countryLabelHi' => ['value' => $fact[5]],
        ], $facts)]];
    }
}
