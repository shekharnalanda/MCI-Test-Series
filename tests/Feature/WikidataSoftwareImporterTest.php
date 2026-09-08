<?php

namespace Tests\Feature;

use App\Models\Question;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WikidataSoftwareImporterTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_imports_only_unambiguous_bilingual_software_facts(): void
    {
        $this->seed();
        Http::fake([
            'https://www.wikidata.org*' => Http::response('ok', 200),
            'https://query.wikidata.org/*' => Http::response($this->response(), 200),
        ]);

        $this->artisan('mci:wikidata-software --limit=20')->assertSuccessful();
        $this->artisan('mci:wikidata-software --limit=20')->assertSuccessful();

        $questions = Question::where('source_reference', 'wikidata-software-developer')->get();
        $this->assertCount(4, $questions);
        $this->assertFalse($questions->contains(fn (Question $question) => str_contains($question->question_text, 'Ambiguous')));
        $this->assertCount(4, $questions->first()->options);
        $this->assertCount(1, $questions->first()->options->where('is_correct', true));
        $this->assertTrue($questions->first()->is_verified);
    }

    private function response(): array
    {
        $facts = [
            ['Q1', 'Software One', 'सॉफ्टवेयर एक', 'Q101', 'Developer One', 'डेवलपर एक'],
            ['Q2', 'Software Two', 'सॉफ्टवेयर दो', 'Q102', 'Developer Two', 'डेवलपर दो'],
            ['Q3', 'Software Three', 'सॉफ्टवेयर तीन', 'Q103', 'Developer Three', 'डेवलपर तीन'],
            ['Q4', 'Software Four', 'सॉफ्टवेयर चार', 'Q104', 'Developer Four', 'डेवलपर चार'],
            ['Q5', 'Ambiguous', 'अस्पष्ट', 'Q105', 'Developer Five', 'डेवलपर पाँच'],
            ['Q5', 'Ambiguous', 'अस्पष्ट', 'Q106', 'Developer Six', 'डेवलपर छह'],
        ];

        return ['results' => ['bindings' => array_map(fn (array $fact) => [
            'software' => ['value' => 'https://www.wikidata.org/entity/'.$fact[0]],
            'softwareLabelEn' => ['value' => $fact[1]],
            'softwareLabelHi' => ['value' => $fact[2]],
            'developer' => ['value' => 'https://www.wikidata.org/entity/'.$fact[3]],
            'developerLabelEn' => ['value' => $fact[4]],
            'developerLabelHi' => ['value' => $fact[5]],
        ], $facts)]];
    }
}
