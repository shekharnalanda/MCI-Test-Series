<?php

namespace Tests\Feature;

use App\Models\Question;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WikidataElementOrderHardQuestionImporterTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_imports_strict_bilingual_hard_questions_idempotently(): void
    {
        $this->seed();
        Http::fake([
            'https://www.wikidata.org/*' => Http::response('ok', 200),
            'https://query.wikidata.org/*' => Http::response($this->response(), 200),
        ]);

        $this->artisan('mci:wikidata-element-order-hard --limit=2')->assertSuccessful();
        $this->artisan('mci:wikidata-element-order-hard --limit=2')->assertSuccessful();

        $questions = Question::where('source_reference', 'like', 'wikidata-element-order:%')->get();
        $this->assertCount(2, $questions);
        $this->assertTrue($questions->every(fn (Question $question) => $question->difficulty === 'hard'
            && $question->verification_status === 'verified' && $question->is_published));
        $this->assertCount(4, $questions->first()->options);
        $this->assertCount(1, $questions->first()->options->where('is_correct', true));
    }

    private function response(): array
    {
        return ['results' => ['bindings' => collect(range(1, 40))->map(fn (int $number) => [
            'element' => ['value' => 'https://www.wikidata.org/entity/Q'.$number],
            'elementLabelEn' => ['value' => 'Element '.$number],
            'elementLabelHi' => ['value' => 'तत्व '.$number],
            'atomicNumber' => ['value' => (string) $number],
        ])->all()]];
    }
}
