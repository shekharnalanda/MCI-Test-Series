<?php

namespace Tests\Feature;

use App\Models\Question;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WikidataSoftwareProgrammingLanguageImporterTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_imports_only_unambiguous_bilingual_programming_language_facts(): void
    {
        $this->seed();
        Http::fake(['https://www.wikidata.org/*' => Http::response('ok', 200),
            'https://query.wikidata.org/*' => Http::response($this->response(), 200)]);
        $this->artisan('mci:wikidata-software-programming-languages --limit=20')->assertSuccessful();
        $this->artisan('mci:wikidata-software-programming-languages --limit=20')->assertSuccessful();
        $questions = Question::where('source_reference', 'wikidata-software-programming-language')->get();
        $this->assertCount(4, $questions);
        $this->assertFalse($questions->contains(fn (Question $question) => str_contains($question->question_text, 'Ambiguous')));
        $this->assertCount(4, $questions->first()->options);
        $this->assertCount(1, $questions->first()->options->where('is_correct', true));
        $this->assertTrue($questions->first()->is_verified);
    }

    private function response(): array
    {
        $facts = [['Q1', 'Software One', 'सॉफ्टवेयर एक', 'Q101', 'C', 'सी'],
            ['Q2', 'Software Two', 'सॉफ्टवेयर दो', 'Q102', 'C++', 'सी++'],
            ['Q3', 'Software Three', 'सॉफ्टवेयर तीन', 'Q103', 'Java', 'जावा'],
            ['Q4', 'Software Four', 'सॉफ्टवेयर चार', 'Q104', 'Python', 'पाइथन'],
            ['Q5', 'Ambiguous', 'अस्पष्ट', 'Q105', 'Rust', 'रस्ट'],
            ['Q5', 'Ambiguous', 'अस्पष्ट', 'Q106', 'Go', 'गो']];

        return ['results' => ['bindings' => array_map(fn (array $fact) => [
            'software' => ['value' => 'https://www.wikidata.org/entity/'.$fact[0]],
            'softwareLabelEn' => ['value' => $fact[1]], 'softwareLabelHi' => ['value' => $fact[2]],
            'language' => ['value' => 'https://www.wikidata.org/entity/'.$fact[3]],
            'languageLabelEn' => ['value' => $fact[4]], 'languageLabelHi' => ['value' => $fact[5]],
        ], $facts)]];
    }
}
