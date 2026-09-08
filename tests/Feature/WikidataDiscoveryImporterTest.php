<?php

namespace Tests\Feature;

use App\Models\Question;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WikidataDiscoveryImporterTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_imports_only_unambiguous_bilingual_discovery_facts(): void
    {
        $this->seed();
        Http::fake([
            'https://www.wikidata.org*' => Http::response('ok', 200),
            'https://query.wikidata.org/*' => Http::response($this->response(), 200),
        ]);

        $this->artisan('mci:wikidata-discoveries --limit=20')->assertSuccessful();
        $this->artisan('mci:wikidata-discoveries --limit=20')->assertSuccessful();

        $questions = Question::where('source_reference', 'wikidata-discovery-inventor')->get();
        $this->assertCount(4, $questions);
        $this->assertFalse($questions->contains(fn (Question $question) => str_contains($question->question_text, 'Ambiguous')));
        $this->assertCount(4, $questions->first()->options);
        $this->assertCount(1, $questions->first()->options->where('is_correct', true));
        $this->assertTrue($questions->first()->is_verified);
    }

    private function response(): array
    {
        $facts = [
            ['Q1', 'Discovery One', 'खोज एक', 'Q101', 'Person One', 'व्यक्ति एक'],
            ['Q2', 'Discovery Two', 'खोज दो', 'Q102', 'Person Two', 'व्यक्ति दो'],
            ['Q3', 'Discovery Three', 'खोज तीन', 'Q103', 'Person Three', 'व्यक्ति तीन'],
            ['Q4', 'Discovery Four', 'खोज चार', 'Q104', 'Person Four', 'व्यक्ति चार'],
            ['Q5', 'Ambiguous', 'अस्पष्ट', 'Q105', 'Person Five', 'व्यक्ति पाँच'],
            ['Q5', 'Ambiguous', 'अस्पष्ट', 'Q106', 'Person Six', 'व्यक्ति छह'],
        ];

        return ['results' => ['bindings' => array_map(fn (array $fact) => [
            'item' => ['value' => 'https://www.wikidata.org/entity/'.$fact[0]],
            'itemLabelEn' => ['value' => $fact[1]],
            'itemLabelHi' => ['value' => $fact[2]],
            'person' => ['value' => 'https://www.wikidata.org/entity/'.$fact[3]],
            'personLabelEn' => ['value' => $fact[4]],
            'personLabelHi' => ['value' => $fact[5]],
        ], $facts)]];
    }
}
