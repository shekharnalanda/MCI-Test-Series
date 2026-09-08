<?php

namespace Tests\Feature;

use App\Models\Question;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WikidataBookAuthorImporterTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_imports_only_unambiguous_bilingual_book_author_facts(): void
    {
        $this->seed();
        Http::fake([
            'https://www.wikidata.org*' => Http::response('ok', 200),
            'https://query.wikidata.org/*' => Http::response($this->response(), 200),
        ]);

        $this->artisan('mci:wikidata-books --limit=20')->assertSuccessful();
        $this->artisan('mci:wikidata-books --limit=20')->assertSuccessful();

        $questions = Question::where('source_reference', 'wikidata-book-author')->get();
        $this->assertCount(4, $questions);
        $this->assertFalse($questions->contains(fn (Question $question) => str_contains($question->question_text, 'Ambiguous')));
        $this->assertCount(4, $questions->first()->options);
        $this->assertCount(1, $questions->first()->options->where('is_correct', true));
        $this->assertTrue($questions->first()->is_verified);
    }

    private function response(): array
    {
        $facts = [
            ['Q1', 'Book One', 'पुस्तक एक', 'Q101', 'Author One', 'लेखक एक'],
            ['Q2', 'Book Two', 'पुस्तक दो', 'Q102', 'Author Two', 'लेखक दो'],
            ['Q3', 'Book Three', 'पुस्तक तीन', 'Q103', 'Author Three', 'लेखक तीन'],
            ['Q4', 'Book Four', 'पुस्तक चार', 'Q104', 'Author Four', 'लेखक चार'],
            ['Q5', 'Ambiguous', 'अस्पष्ट', 'Q105', 'Author Five', 'लेखक पाँच'],
            ['Q5', 'Ambiguous', 'अस्पष्ट', 'Q106', 'Author Six', 'लेखक छह'],
        ];

        return ['results' => ['bindings' => array_map(fn (array $fact) => [
            'book' => ['value' => 'https://www.wikidata.org/entity/'.$fact[0]],
            'bookLabelEn' => ['value' => $fact[1]],
            'bookLabelHi' => ['value' => $fact[2]],
            'author' => ['value' => 'https://www.wikidata.org/entity/'.$fact[3]],
            'authorLabelEn' => ['value' => $fact[4]],
            'authorLabelHi' => ['value' => $fact[5]],
        ], $facts)]];
    }
}
