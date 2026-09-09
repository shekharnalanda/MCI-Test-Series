<?php

namespace Tests\Feature;

use App\Models\Question;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WikidataOrganizationHeadquartersImporterTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_imports_only_unambiguous_bilingual_headquarters_facts(): void
    {
        $this->seed();
        Http::fake([
            'https://www.wikidata.org*' => Http::response('ok', 200),
            'https://query.wikidata.org/*' => Http::response($this->response(), 200),
        ]);

        $this->artisan('mci:wikidata-organization-headquarters --limit=20')->assertSuccessful();
        $this->artisan('mci:wikidata-organization-headquarters --limit=20')->assertSuccessful();

        $questions = Question::where('source_reference', 'wikidata-international-organization-headquarters')->get();
        $this->assertCount(4, $questions);
        $this->assertFalse($questions->contains(fn (Question $question) => str_contains($question->question_text, 'Ambiguous')));
        $this->assertCount(4, $questions->first()->options);
        $this->assertCount(1, $questions->first()->options->where('is_correct', true));
        $this->assertTrue($questions->first()->is_verified);
    }

    private function response(): array
    {
        $facts = [
            ['Q1', 'Organization One', 'संगठन एक', 'Q101', 'City One', 'नगर एक'],
            ['Q2', 'Organization Two', 'संगठन दो', 'Q102', 'City Two', 'नगर दो'],
            ['Q3', 'Organization Three', 'संगठन तीन', 'Q103', 'City Three', 'नगर तीन'],
            ['Q4', 'Organization Four', 'संगठन चार', 'Q104', 'City Four', 'नगर चार'],
            ['Q5', 'Ambiguous', 'अस्पष्ट', 'Q105', 'City Five', 'नगर पाँच'],
            ['Q5', 'Ambiguous', 'अस्पष्ट', 'Q106', 'City Six', 'नगर छह'],
        ];

        return ['results' => ['bindings' => array_map(fn (array $fact) => [
            'organization' => ['value' => 'https://www.wikidata.org/entity/'.$fact[0]],
            'organizationLabelEn' => ['value' => $fact[1]],
            'organizationLabelHi' => ['value' => $fact[2]],
            'headquarters' => ['value' => 'https://www.wikidata.org/entity/'.$fact[3]],
            'headquartersLabelEn' => ['value' => $fact[4]],
            'headquartersLabelHi' => ['value' => $fact[5]],
        ], $facts)]];
    }
}
