<?php

namespace Tests\Feature;

use App\Models\Question;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WikidataChemicalElementImporterTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_builds_two_bilingual_question_families_and_deduplicates_reimports(): void
    {
        $this->seed();
        Http::fake([
            'https://www.wikidata.org*' => Http::response('ok', 200),
            'https://query.wikidata.org/*' => Http::response($this->response(), 200),
        ]);

        $this->artisan('mci:wikidata-elements --limit=10')->assertSuccessful();
        $this->artisan('mci:wikidata-elements --limit=10')->assertSuccessful();

        $questions = Question::whereIn('source_reference', [
            'wikidata-element-symbol',
            'wikidata-element-atomic-number',
        ])->get();

        $this->assertCount(8, $questions);
        $this->assertSame(4, $questions->where('source_reference', 'wikidata-element-symbol')->count());
        $this->assertSame(4, $questions->where('source_reference', 'wikidata-element-atomic-number')->count());
        $this->assertFalse($questions->contains(fn (Question $question) => blank($question->question_text_hi)));
        $this->assertFalse($questions->contains(fn (Question $question) => $question->options->count() !== 4));
        $this->assertFalse($questions->contains(fn (Question $question) => $question->options->where('is_correct', true)->count() !== 1));
    }

    private function response(): array
    {
        $facts = [
            ['Q1', 'Hydrogen', 'हाइड्रोजन', 'H', '1'],
            ['Q2', 'Helium', 'हीलियम', 'He', '2'],
            ['Q3', 'Lithium', 'लिथियम', 'Li', '3'],
            ['Q4', 'Beryllium', 'बेरिलियम', 'Be', '4'],
        ];

        return ['results' => ['bindings' => array_map(fn (array $fact) => [
            'element' => ['value' => 'https://www.wikidata.org/entity/'.$fact[0]],
            'elementLabelEn' => ['value' => $fact[1]],
            'elementLabelHi' => ['value' => $fact[2]],
            'symbol' => ['value' => $fact[3]],
            'atomicNumber' => ['value' => $fact[4]],
        ], $facts)]];
    }
}
