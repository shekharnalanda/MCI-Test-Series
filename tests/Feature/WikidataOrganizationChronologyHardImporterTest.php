<?php

namespace Tests\Feature;

use App\Models\ContentSource;
use App\Models\Question;
use App\Services\QuestionIngestionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WikidataOrganizationChronologyHardImporterTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_builds_idempotent_hard_chronology_questions_from_verified_facts(): void
    {
        $this->seed();
        Http::fake(['https://www.wikidata.org/*' => Http::response('ok', 200)]);
        $source = ContentSource::where('slug', 'wikidata')->firstOrFail();
        $ingestion = app(QuestionIngestionService::class);

        foreach (range(1, 40) as $number) {
            $ingestion->ingest([$this->fact($number)], $source, 'json');
        }

        $this->artisan('mci:wikidata-organization-chronology-hard --limit=2')->assertSuccessful();
        $this->artisan('mci:wikidata-organization-chronology-hard --limit=2')->assertSuccessful();

        $questions = Question::where('source_reference', 'like', 'wikidata-organization-chronology:%')->get();
        $this->assertCount(2, $questions);
        $this->assertTrue($questions->every(fn (Question $question) => $question->difficulty === 'hard'
            && $question->verification_status === 'verified' && $question->is_published));
        $this->assertCount(4, $questions->first()->options);
        $this->assertCount(1, $questions->first()->options->where('is_correct', true));
    }

    private function fact(int $number): array
    {
        $year = 1800 + $number;

        return [
            'question_text' => "In which year was Organization {$number} established?",
            'question_text_hi' => "संगठन {$number} की स्थापना किस वर्ष हुई थी?",
            'explanation' => "Organization {$number} was established in {$year}.",
            'explanation_hi' => "संगठन {$number} की स्थापना {$year} में हुई थी।",
            'subject_id' => \App\Models\Subject::where('name', 'General Knowledge')->value('id'),
            'topic_id' => \App\Models\Topic::where('name', 'Organizations')->value('id'),
            'exam_ids' => [], 'difficulty' => 'medium', 'language' => 'bilingual',
            'source_url' => 'https://www.wikidata.org/entity/Q'.(1000 + $number),
            'source_reference' => 'wikidata-international-organization-inception-year',
            'source_published_at' => now()->toDateString(), 'generation_method' => 'automated',
            'options' => collect([$year, $year + 1, $year + 2, $year + 3])->map(fn (int $option) => [
                'option_text' => (string) $option, 'option_text_hi' => (string) $option, 'is_correct' => $option === $year,
            ])->all(),
        ];
    }
}
