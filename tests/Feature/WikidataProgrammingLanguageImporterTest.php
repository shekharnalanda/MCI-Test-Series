<?php

namespace Tests\Feature;

use App\Models\Question;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WikidataProgrammingLanguageImporterTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_imports_only_unambiguous_bilingual_programming_language_facts(): void
    {
        $this->seed();
        Http::fake([
            'https://www.wikidata.org*' => Http::response('ok', 200),
            'https://query.wikidata.org/*' => Http::response($this->response(), 200),
        ]);

        $this->artisan('mci:wikidata-programming-languages --limit=20')->assertSuccessful();
        $this->artisan('mci:wikidata-programming-languages --limit=20')->assertSuccessful();

        $questions = Question::where('source_reference', 'wikidata-programming-language-designer')->get();
        $this->assertCount(4, $questions);
        $this->assertFalse($questions->contains(fn (Question $question) => str_contains($question->question_text, 'Ambiguous')));
        $this->assertCount(4, $questions->first()->options);
        $this->assertCount(1, $questions->first()->options->where('is_correct', true));
        $this->assertTrue($questions->first()->is_verified);
    }

    private function response(): array
    {
        $facts = [
            ['Q1', 'Language One', 'भाषा एक', 'Q101', 'Designer One', 'डिजाइनर एक'],
            ['Q2', 'Language Two', 'भाषा दो', 'Q102', 'Designer Two', 'डिजाइनर दो'],
            ['Q3', 'Language Three', 'भाषा तीन', 'Q103', 'Designer Three', 'डिजाइनर तीन'],
            ['Q4', 'Language Four', 'भाषा चार', 'Q104', 'Designer Four', 'डिजाइनर चार'],
            ['Q5', 'Ambiguous', 'अस्पष्ट', 'Q105', 'Designer Five', 'डिजाइनर पाँच'],
            ['Q5', 'Ambiguous', 'अस्पष्ट', 'Q106', 'Designer Six', 'डिजाइनर छह'],
        ];

        return ['results' => ['bindings' => array_map(fn (array $fact) => [
            'language' => ['value' => 'https://www.wikidata.org/entity/'.$fact[0]],
            'languageLabelEn' => ['value' => $fact[1]],
            'languageLabelHi' => ['value' => $fact[2]],
            'designer' => ['value' => 'https://www.wikidata.org/entity/'.$fact[3]],
            'designerLabelEn' => ['value' => $fact[4]],
            'designerLabelHi' => ['value' => $fact[5]],
        ], $facts)]];
    }
}
