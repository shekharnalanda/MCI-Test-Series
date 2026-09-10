<?php

namespace Tests\Feature;

use App\Models\Question;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WikidataSoftwareChronologyHardImporterTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_builds_idempotent_verified_bilingual_hard_questions(): void
    {
        $this->seed();
        Http::fake(['https://www.wikidata.org/*' => Http::response('ok', 200),
            'https://query.wikidata.org/*' => Http::response($this->response(), 200)]);
        $this->artisan('mci:wikidata-software-release-years --limit=40')->assertSuccessful();
        $this->artisan('mci:wikidata-software-chronology-hard --limit=2')->assertSuccessful();
        $this->artisan('mci:wikidata-software-chronology-hard --limit=2')->assertSuccessful();

        $questions = Question::where('source_reference', 'like', 'wikidata-software-chronology:%')->get();
        $this->assertCount(2, $questions);
        $this->assertTrue($questions->every(fn (Question $question) => $question->difficulty === 'hard'
            && $question->verification_status === 'verified' && $question->is_published));
        $this->assertCount(4, $questions->first()->options);
        $this->assertCount(1, $questions->first()->options->where('is_correct', true));
    }

    private function response(): array
    {
        return ['results' => ['bindings' => collect(range(1, 40))->map(fn (int $number) => [
            'software' => ['value' => 'https://www.wikidata.org/entity/Q'.(2000 + $number)],
            'softwareLabelEn' => ['value' => 'Software '.$number],
            'softwareLabelHi' => ['value' => 'सॉफ्टवेयर '.$number],
            'release' => ['value' => (1950 + $number).'-01-01T00:00:00Z'],
        ])->all()]];
    }
}
