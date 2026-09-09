<?php

namespace Tests\Feature;

use App\Models\Question;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WikidataUniversityCountryImporterTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_imports_only_single_country_bilingual_universities_idempotently(): void
    {
        $this->seed();
        Http::fake(['https://www.wikidata.org/*' => Http::response('ok', 200),
            'https://query.wikidata.org/*' => Http::response($this->response(), 200)]);
        $this->artisan('mci:wikidata-university-countries --limit=20')->assertSuccessful();
        $this->artisan('mci:wikidata-university-countries --limit=20')->assertSuccessful();
        $questions = Question::where('source_reference', 'wikidata-university-country')->get();
        $this->assertCount(4, $questions);
        $this->assertFalse($questions->contains(fn (Question $question) => str_contains($question->question_text, 'Ambiguous')));
        $this->assertCount(4, $questions->first()->options);
        $this->assertCount(1, $questions->first()->options->where('is_correct', true));
        $this->assertTrue($questions->first()->is_verified);
    }

    private function response(): array
    {
        $facts = [['Q1', 'University One', 'विश्वविद्यालय एक', 'Q101', 'India', 'भारत'],
            ['Q2', 'University Two', 'विश्वविद्यालय दो', 'Q102', 'Nepal', 'नेपाल'],
            ['Q3', 'University Three', 'विश्वविद्यालय तीन', 'Q103', 'Bhutan', 'भूटान'],
            ['Q4', 'University Four', 'विश्वविद्यालय चार', 'Q104', 'Sri Lanka', 'श्रीलंका'],
            ['Q5', 'Ambiguous University', 'अस्पष्ट विश्वविद्यालय', 'Q105', 'France', 'फ्रांस'],
            ['Q5', 'Ambiguous University', 'अस्पष्ट विश्वविद्यालय', 'Q106', 'Spain', 'स्पेन']];

        return ['results' => ['bindings' => array_map(fn (array $fact) => [
            'university' => ['value' => 'https://www.wikidata.org/entity/'.$fact[0]],
            'universityLabelEn' => ['value' => $fact[1]], 'universityLabelHi' => ['value' => $fact[2]],
            'country' => ['value' => 'https://www.wikidata.org/entity/'.$fact[3]],
            'countryLabelEn' => ['value' => $fact[4]], 'countryLabelHi' => ['value' => $fact[5]],
        ], $facts)]];
    }
}
