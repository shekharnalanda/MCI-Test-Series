<?php

namespace App\Services;

use App\Models\ContentSource;
use App\Models\Question;
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
            'topic' => 'Countries Capitals Currencies',
            'reference' => 'wikidata-country-currency',
            'question_en' => 'What is the currency of %s?',
            'question_hi' => '%s की मुद्रा क्या है?',
            'explanation_en' => '%s is the currency of %s.',
            'explanation_hi' => '%s, %s की मुद्रा है।',
        ],
        'continent' => [
            'property' => 'P30',
            'topic' => 'Countries Capitals Currencies',
            'reference' => 'wikidata-country-continent',
            'question_en' => 'On which continent is %s located?',
            'question_hi' => '%s किस महाद्वीप में स्थित है?',
            'explanation_en' => '%2$s is located in %1$s.',
            'explanation_hi' => '%2$s, %1$s में स्थित है।',
        ],
        'official-language' => [
            'property' => 'P37',
            'topic' => 'Countries Capitals Currencies',
            'reference' => 'wikidata-country-official-language',
            'question_en' => 'What is the official language of %s?',
            'question_hi' => '%s की आधिकारिक भाषा क्या है?',
            'explanation_en' => '%s is an official language of %s.',
            'explanation_hi' => '%s, %s की आधिकारिक भाषा है।',
        ],
        'india-state-capital' => [
            'property' => 'P36',
            'topic' => 'Countries Capitals Currencies',
            'reference' => 'wikidata-india-state-capital',
            'question_en' => 'What is the capital of %s?',
            'question_hi' => '%s की राजधानी क्या है?',
            'explanation_en' => '%s is the capital of %s.',
            'explanation_hi' => '%s, %s की राजधानी है।',
        ],
        'national-anthem' => [
            'property' => 'P85',
            'topic' => 'National Symbols',
            'reference' => 'wikidata-country-national-anthem',
            'question_en' => 'What is the national anthem of %s?',
            'question_hi' => '%s का राष्ट्रगान कौन-सा है?',
            'explanation_en' => '%s is the national anthem of %s.',
            'explanation_hi' => '%s, %s का राष्ट्रगान है।',
        ],
        'iso-code' => [
            'property' => 'P297',
            'topic' => 'Countries Capitals Currencies',
            'literal' => true,
            'reference' => 'wikidata-country-iso-code',
            'question_en' => 'What is the ISO alpha-2 code of %s?',
            'question_hi' => '%s का ISO alpha-2 कोड क्या है?',
            'explanation_en' => '%s is the ISO alpha-2 code of %s.',
            'explanation_hi' => '%s, %s का ISO alpha-2 कोड है।',
        ],
        'calling-code' => [
            'property' => 'P474',
            'topic' => 'Countries Capitals Currencies',
            'literal' => true,
            'reference' => 'wikidata-country-calling-code',
            'question_en' => 'What is the international calling code of %s?',
            'question_hi' => '%s का अंतरराष्ट्रीय कॉलिंग कोड क्या है?',
            'explanation_en' => '%s is the international calling code of %s.',
            'explanation_hi' => '%s, %s का अंतरराष्ट्रीय कॉलिंग कोड है।',
        ],
        'iso-alpha-3-code' => [
            'property' => 'P298',
            'topic' => 'Countries Capitals Currencies',
            'literal' => true,
            'reference' => 'wikidata-country-iso-alpha-3-code',
            'question_en' => 'What is the ISO alpha-3 code of %s?',
            'question_hi' => '%s का ISO alpha-3 कोड क्या है?',
            'explanation_en' => '%s is the ISO alpha-3 code of %s.',
            'explanation_hi' => '%s, %s का ISO alpha-3 कोड है।',
        ],
        'iso-numeric-code' => [
            'property' => 'P299',
            'topic' => 'Countries Capitals Currencies',
            'literal' => true,
            'reference' => 'wikidata-country-iso-numeric-code',
            'question_en' => 'What is the ISO numeric code of %s?',
            'question_hi' => '%s का ISO संख्यात्मक कोड क्या है?',
            'explanation_en' => '%s is the ISO numeric code of %s.',
            'explanation_hi' => '%s, %s का ISO संख्यात्मक कोड है।',
        ],
        'internet-domain' => [
            'property' => 'P78',
            'topic' => 'Countries Capitals Currencies',
            'literal' => true,
            'reference' => 'wikidata-country-internet-domain',
            'question_en' => 'What is the country-code top-level internet domain of %s?',
            'question_hi' => '%s का country-code top-level internet domain क्या है?',
            'explanation_en' => '%s is a country-code top-level internet domain of %s.',
            'explanation_hi' => '%s, %s का country-code top-level internet domain है।',
        ],
        'capital' => [
            'property' => 'P36',
            'topic' => 'Countries Capitals Currencies',
            'reference' => 'wikidata-country-capital',
            'question_en' => 'What is the capital of %s?',
            'question_hi' => '%s की राजधानी क्या है?',
            'explanation_en' => '%s is the capital of %s.',
            'explanation_hi' => '%s, %s की राजधानी है।',
        ],
        'highest-point' => [
            'property' => 'P610',
            'topic' => 'Countries Capitals Currencies',
            'reference' => 'wikidata-country-highest-point',
            'question_en' => 'What is the highest point of %s?',
            'question_hi' => '%s का सर्वोच्च स्थल कौन-सा है?',
            'explanation_en' => '%s is the highest point of %s.',
            'explanation_hi' => '%s, %s का सर्वोच्च स्थल है।',
        ],
        'lowest-point' => [
            'property' => 'P1589',
            'topic' => 'Countries Capitals Currencies',
            'reference' => 'wikidata-country-lowest-point',
            'question_en' => 'What is the lowest point of %s?',
            'question_hi' => '%s का निम्नतम स्थल कौन-सा है?',
            'explanation_en' => '%s is the lowest point of %s.',
            'explanation_hi' => '%s, %s का निम्नतम स्थल है।',
        ],
    ];

    public function __construct(
        private readonly QuestionIngestionService $ingestion,
        private readonly TrustedSourcePolicy $sourcePolicy,
        private readonly TrustedSourceHealthService $health,
    ) {}

    public function import(string $family, int $limit = 200, bool $dryRun = false): array
    {
        $definition = self::FAMILIES[$family] ?? throw new InvalidArgumentException('Unsupported country fact family.');
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
                'query' => $this->query($family, $definition['property'], (bool) ($definition['literal'] ?? false), $limit),
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
            ->where('name', $definition['topic'])
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
                'explanation' => sprintf($definition['explanation_en'], $fact['answer_en'], $fact['country_en']),
                'explanation_hi' => sprintf($definition['explanation_hi'], $fact['answer_hi'], $fact['country_hi']),
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

        // Refresh explanations for already imported facts as source facts are reprocessed.
        foreach ($questions as $question) {
            Question::where('source_reference', $question['source_reference'])
                ->where('source_url', $question['source_url'])
                ->update([
                    'explanation' => $question['explanation'],
                    'explanation_hi' => $question['explanation_hi'],
                ]);
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

    private function query(string $family, string $property, bool $literal, int $limit): string
    {
        $entityPattern = $family === 'india-state-capital'
            ? 'VALUES ?administrativeType { wd:Q12443800 wd:Q467745 }'.PHP_EOL.'  ?country wdt:P31 ?administrativeType;'
            : '?country wdt:P31 wd:Q3624078;';
        $answerPattern = $literal
            ? 'BIND(STR(?answer) AS ?answerLabelEn)'.PHP_EOL.'  BIND(STR(?answer) AS ?answerLabelHi)'
            : '?answer rdfs:label ?answerLabelEn;'.PHP_EOL.'          rdfs:label ?answerLabelHi.';
        $answerLanguageFilters = $literal
            ? ''
            : 'FILTER(LANG(?answerLabelEn) = "en")'.PHP_EOL.'  FILTER(LANG(?answerLabelHi) = "hi")';

        return <<<SPARQL
SELECT DISTINCT ?country ?countryLabelEn ?countryLabelHi ?answer ?answerLabelEn ?answerLabelHi WHERE {
  {$entityPattern}
           wdt:{$property} ?answer;
           rdfs:label ?countryLabelEn;
           rdfs:label ?countryLabelHi.
  {$answerPattern}
  FILTER NOT EXISTS { ?country wdt:P576 ?dissolvedDate. }
  FILTER(LANG(?countryLabelEn) = "en")
  FILTER(LANG(?countryLabelHi) = "hi")
  {$answerLanguageFilters}
}
ORDER BY ?countryLabelEn ?answerLabelEn
LIMIT {$limit}
SPARQL;
    }
}
