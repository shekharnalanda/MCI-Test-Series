<?php

namespace Tests\Feature;

use App\Models\Question;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WikidataAwardInceptionYearImporterTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_imports_only_unambiguous_bilingual_award_years_idempotently(): void
    {
        $this->seed();
        Http::fake(['https://www.wikidata.org/*' => Http::response('ok', 200),
            'https://query.wikidata.org/*' => Http::response($this->response(), 200)]);
        $this->artisan('mci:wikidata-award-inception-years --limit=20')->assertSuccessful();
        $this->artisan('mci:wikidata-award-inception-years --limit=20')->assertSuccessful();
        $questions = Question::where('source_reference', 'wikidata-award-inception-year')->get();
        $this->assertCount(4, $questions);
        $this->assertFalse($questions->contains(fn (Question $question) => str_contains($question->question_text, 'Ambiguous')));
        $this->assertCount(4, $questions->first()->options);
        $this->assertCount(1, $questions->first()->options->where('is_correct', true));
        $this->assertTrue($questions->first()->is_verified);
    }

    private function response(): array
    {
        $facts = [['Q1', 'Award One', 'पुरस्कार एक', '1951-01-01T00:00:00Z'],
            ['Q2', 'Award Two', 'पुरस्कार दो', '1962-01-01T00:00:00Z'],
            ['Q3', 'Award Three', 'पुरस्कार तीन', '1973-01-01T00:00:00Z'],
            ['Q4', 'Award Four', 'पुरस्कार चार', '1984-01-01T00:00:00Z'],
            ['Q5', 'Ambiguous Award', 'अस्पष्ट पुरस्कार', '1995-01-01T00:00:00Z'],
            ['Q5', 'Ambiguous Award', 'अस्पष्ट पुरस्कार', '1996-01-01T00:00:00Z']];

        return ['results' => ['bindings' => array_map(fn (array $fact) => [
            'award' => ['value' => 'https://www.wikidata.org/entity/'.$fact[0]],
            'awardLabelEn' => ['value' => $fact[1]], 'awardLabelHi' => ['value' => $fact[2]],
            'inception' => ['value' => $fact[3]],
        ], $facts)]];
    }
}
