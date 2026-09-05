<?php

namespace App\Services;

use App\Models\ContentSource;
use App\Models\Subject;
use App\Models\Topic;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class WikidataCountryCapitalImporter
{
    private const ENDPOINT = 'https://query.wikidata.org/sparql';

    public function __construct(
        private readonly QuestionIngestionService $ingestion,
        private readonly TrustedSourcePolicy $sourcePolicy,
        private readonly TrustedSourceHealthService $health,
    ) {}

    public function import(int $limit = 100, bool $dryRun = false): array
    {
        $limit = max(10, min($limit, 200));
        $source = ContentSource::where('slug', 'wikidata')->where('is_active', true)->firstOrFail();
        $this->health->check($source);
        $source->refresh();

        if (! $this->sourcePolicy->canGenerateQuestions($source)) {
            throw new RuntimeException('Wikidata has not passed the trusted-source policy.');
        }

        $response = Http::withHeaders([
            'Accept' => 'application/sparql-results+json',
        ])
            ->withUserAgent('MCI-Test-Series/1.0 (+https://test.mciedu.com)')
            ->timeout(30)
            ->retry(2, 500, throw: false)
            ->get(self::ENDPOINT, [
                'query' => $this->query($limit),
                'format' => 'json',
            ]);

        if (! $response->successful()) {
            throw new RuntimeException('Wikidata query failed with HTTP '.$response->status().'.');
        }

        $facts = collect($response->json('results.bindings', []))
            ->map(fn (array $row) => $this->fact($row))
            ->filter()
            ->unique('country_url')
            ->values();

        $capitals = $facts->unique('capital_url')->values();

        if ($capitals->count() < 4) {
            throw new RuntimeException('At least four complete bilingual country-capital facts are required.');
        }

        $subject = Subject::where('name', 'Static GK')->firstOrFail();
        $topic = Topic::where('subject_id', $subject->id)
            ->where('name', 'Countries Capitals Currencies')
            ->firstOrFail();
        $examIds = $subject->exams()->where('is_active', true)->pluck('exams.id')->all();

        $questions = $facts->map(function (array $fact) use ($capitals, $subject, $topic, $examIds): array {
            $distractors = $capitals
                ->reject(fn (array $candidate) => $candidate['capital_url'] === $fact['capital_url'])
                ->sortBy(fn (array $candidate) => hash('sha256', $fact['country_url'].'|'.$candidate['capital_url']))
                ->take(3);

            $options = $distractors->push($fact)
                ->sortBy(fn (array $candidate) => hash('sha256', $fact['country_url'].'|option|'.$candidate['capital_url']))
                ->values()
                ->map(fn (array $candidate) => [
                    'option_text' => $candidate['capital_en'],
                    'option_text_hi' => $candidate['capital_hi'],
                    'is_correct' => $candidate['capital_url'] === $fact['capital_url'],
                ])
                ->all();

            return [
                'question_text' => "What is the capital of {$fact['country_en']}?",
                'question_text_hi' => "{$fact['country_hi']} की राजधानी क्या है?",
                'explanation' => "{$fact['capital_en']} is the capital of {$fact['country_en']}.",
                'explanation_hi' => "{$fact['capital_hi']}, {$fact['country_hi']} की राजधानी है।",
                'subject_id' => $subject->id,
                'topic_id' => $topic->id,
                'exam_ids' => $examIds,
                'difficulty' => 'easy',
                'language' => 'bilingual',
                'source_url' => $fact['country_url'],
                'source_reference' => 'wikidata-country-capital',
                'source_published_at' => now()->toDateString(),
                'generation_method' => 'automated',
                'options' => $options,
            ];
        })->all();

        if ($dryRun) {
            return ['fetched' => count($questions), 'accepted' => count($questions), 'duplicates' => 0, 'rejected' => 0, 'dry_run' => true];
        }

        $batch = $this->ingestion->ingest($questions, $source, 'json');

        return [
            'fetched' => count($questions),
            'accepted' => $batch->accepted_count,
            'duplicates' => $batch->duplicate_count,
            'rejected' => $batch->rejected_count,
            'dry_run' => false,
        ];
    }

    private function fact(array $row): ?array
    {
        $fact = [
            'country_url' => data_get($row, 'country.value'),
            'country_en' => data_get($row, 'countryLabelEn.value'),
            'country_hi' => data_get($row, 'countryLabelHi.value'),
            'capital_url' => data_get($row, 'capital.value'),
            'capital_en' => data_get($row, 'capitalLabelEn.value'),
            'capital_hi' => data_get($row, 'capitalLabelHi.value'),
        ];

        return collect($fact)->every(fn ($value) => is_string($value) && trim($value) !== '') ? $fact : null;
    }

    private function query(int $limit): string
    {
        return <<<SPARQL
SELECT DISTINCT ?country ?countryLabelEn ?countryLabelHi ?capital ?capitalLabelEn ?capitalLabelHi WHERE {
  ?country wdt:P31 wd:Q3624078;
           wdt:P36 ?capital;
           rdfs:label ?countryLabelEn;
           rdfs:label ?countryLabelHi.
  ?capital rdfs:label ?capitalLabelEn;
           rdfs:label ?capitalLabelHi.
  FILTER(LANG(?countryLabelEn) = "en")
  FILTER(LANG(?countryLabelHi) = "hi")
  FILTER(LANG(?capitalLabelEn) = "en")
  FILTER(LANG(?capitalLabelHi) = "hi")
}
ORDER BY ?countryLabelEn
LIMIT {$limit}
SPARQL;
    }
}
