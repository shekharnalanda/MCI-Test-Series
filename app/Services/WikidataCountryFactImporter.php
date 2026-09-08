<?php

namespace App\Services;

use App\Models\ContentSource;
use App\Models\Subject;
use App\Models\Topic;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use RuntimeException;

class WikidataCountryFactImporter
{
    private const ENDPOINT = 'https://query.wikidata.org/sparql';

    private const FAMILIES = [
        'currency' => [
            'property' => 'P38',
            'reference' => 'wikidata-country-currency',
            'question_en' => 'What is the currency of %s?',
            'question_hi' => '%s की मुद्रा क्या है?',
            'explanation_en' => '%s is the currency of %s.',
            'explanation_hi' => '%s, %s की मुद्रा है।',
        ],
        'continent' => [
            'property' => 'P30',
            'reference' => 'wikidata-country-continent',
            'question_en' => 'On which continent is %s located?',
            'question_hi' => '%s किस महाद्वीप में स्थित है?',
            'explanation_en' => '%s is located in %s.',
            'explanation_hi' => '%s, %s में स्थित है।',
        ],
    ];

    public function __construct(
        private readonly QuestionIngestionService $ingestion,
        private readonly TrustedSourcePolicy $sourcePolicy,
        private readonly TrustedSourceHealthService $health,
    ) {}

    public function import(string $family, int $limit = 200, bool $dryRun = false): array
    {
        $definition = self::FAMILIES[$family] ?? throw new InvalidArgumentException('Supported fact families: currency, continent.');
        $limit = max(10, min($limit, 500));
        $source = ContentSource::where('slug', 'wikidata')->where('is_active', true)->firstOrFail();
        $this->health->check($source);
        $source->refresh();

        if (! $this->sourcePolicy->canGenerateQuestions($source)) {
            throw new RuntimeException('Wikidata has not passed the trusted-source policy.');
        }

        $response = Http::withHeaders(['Accept' => 'application/sparql-results+json'])
            ->withUserAgent('MCI-Test-Series/1.0 (+https://test.mciedu.com)')
            ->timeout(45)
            ->retry(2, 750, throw: false)
            ->get(self::ENDPOINT, [
                'query' => $this->query($definition['property'], $limit),
                'format' => 'json',
            ]);

        if (! $response->successful()) {
            throw new RuntimeException('Wikidata query failed with HTTP '.$response->status().'.');
        }

        $facts = collect($response->json('results.bindings', []))
            ->map(fn (array $row) => $this->fact($row))
            ->filter()
            ->groupBy('country_url')
            // Ambiguous multi-valued facts never become questions.
            ->filter(fn (Collection $rows) => $rows->unique('answer_url')->count() === 1)
            ->map(fn (Collection $rows) => $rows->first())
            ->values();

        $answers = $facts->unique('answer_url')->values();
        if ($answers->count() < 4) {
            throw new RuntimeException('At least four unambiguous complete bilingual facts are required.');
        }

        $subject = Subject::where('name', 'Static GK')->firstOrFail();
        $topic = Topic::where('subject_id', $subject->id)
            ->where('name', 'Countries Capitals Currencies')
            ->firstOrFail();
        $examIds = $subject->exams()->where('is_active', true)->pluck('exams.id')->all();

        $questions = $facts->map(function (array $fact) use ($answers, $definition, $subject, $topic, $examIds): array {
            $options = $answers
                ->reject(fn (array $candidate) => $candidate['answer_url'] === $fact['answer_url'])
                ->sortBy(fn (array $candidate) => hash('sha256', $fact['country_url'].'|'.$candidate['answer_url']))
                ->take(3)
                ->push($fact)
                ->sortBy(fn (array $candidate) => hash('sha256', $fact['country_url'].'|option|'.$candidate['answer_url']))
                ->values()
                ->map(fn (array $candidate) => [
                    'option_text' => $candidate['answer_en'],
                    'option_text_hi' => $candidate['answer_hi'],
                    'is_correct' => $candidate['answer_url'] === $fact['answer_url'],
                ])->all();

            return [
                'question_text' => sprintf($definition['question_en'], $fact['country_en']),
                'question_text_hi' => sprintf($definition['question_hi'], $fact['country_hi']),
                'explanation' => sprintf($definition['explanation_en'], $fact['country_en'], $fact['answer_en']),
                'explanation_hi' => sprintf($definition['explanation_hi'], $fact['country_hi'], $fact['answer_hi']),
                'subject_id' => $subject->id,
                'topic_id' => $topic->id,
                'exam_ids' => $examIds,
                'difficulty' => 'easy',
                'language' => 'bilingual',
                'source_url' => $fact['country_url'],
                'source_reference' => $definition['reference'],
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
            'answer_url' => data_get($row, 'answer.value'),
            'answer_en' => data_get($row, 'answerLabelEn.value'),
            'answer_hi' => data_get($row, 'answerLabelHi.value'),
        ];

        return collect($fact)->every(fn ($value) => is_string($value) && trim($value) !== '') ? $fact : null;
    }

    private function query(string $property, int $limit): string
    {
        return <<<SPARQL
SELECT DISTINCT ?country ?countryLabelEn ?countryLabelHi ?answer ?answerLabelEn ?answerLabelHi WHERE {
  ?country wdt:P31 wd:Q3624078;
           wdt:{$property} ?answer;
           rdfs:label ?countryLabelEn;
           rdfs:label ?countryLabelHi.
  ?answer rdfs:label ?answerLabelEn;
          rdfs:label ?answerLabelHi.
  FILTER(LANG(?countryLabelEn) = "en")
  FILTER(LANG(?countryLabelHi) = "hi")
  FILTER(LANG(?answerLabelEn) = "en")
  FILTER(LANG(?answerLabelHi) = "hi")
}
ORDER BY ?countryLabelEn ?answerLabelEn
LIMIT {$limit}
SPARQL;
    }
}
