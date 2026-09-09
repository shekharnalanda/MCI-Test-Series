<?php

namespace Tests\Feature;

use App\Models\Question;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WikidataChemicalElementAtomicNumberImporterTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_imports_verified_bilingual_atomic_number_facts_idempotently(): void
    {
        $this->seed();
        Http::fake(['https://www.wikidata.org/*' => Http::response('ok', 200),
            'https://query.wikidata.org/*' => Http::response($this->response(), 200)]);
        $this->artisan('mci:wikidata-element-atomic-numbers --limit=20')->assertSuccessful();
        $this->artisan('mci:wikidata-element-atomic-numbers --limit=20')->assertSuccessful();
        $questions = Question::where('source_reference', 'wikidata-chemical-element-atomic-number')->get();
        $this->assertCount(4, $questions);
        $this->assertCount(4, $questions->first()->options);
        $this->assertCount(1, $questions->first()->options->where('is_correct', true));
        $this->assertNotSame($questions->first()->options->first()->option_text, $questions->first()->options->first()->option_text_hi);
        $this->assertTrue($questions->first()->is_verified);
    }

    private function response(): array
    {
        $facts = [['Q1', 'Hydrogen', 'हाइड्रोजन', '1'], ['Q2', 'Helium', 'हीलियम', '2'],
            ['Q3', 'Lithium', 'लिथियम', '3'], ['Q4', 'Beryllium', 'बेरिलियम', '4']];

        return ['results' => ['bindings' => array_map(fn (array $fact) => [
            'element' => ['value' => 'https://www.wikidata.org/entity/'.$fact[0]],
            'elementLabelEn' => ['value' => $fact[1]], 'elementLabelHi' => ['value' => $fact[2]],
            'atomicNumber' => ['value' => $fact[3]],
        ], $facts)]];
    }
}
