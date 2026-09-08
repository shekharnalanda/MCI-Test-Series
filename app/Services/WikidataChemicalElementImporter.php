<?php

namespace App\Services;

use App\Models\ContentSource;
use App\Models\Subject;
use App\Models\Topic;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class WikidataChemicalElementImporter
{
    private const ENDPOINT = 'https://query.wikidata.org/sparql';

    public function __construct(
        private readonly QuestionIngestionService $ingestion,
        private readonly TrustedSourcePolicy $sourcePolicy,
        private readonly TrustedSourceHealthService $health,
    ) {}

    public function import(int $limit = 150, bool $dryRun = false): array
    {
        $limit = max(10, min($limit, 200));
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
            ->get(self::ENDPOINT, ['query' => $this->query($limit), 'format' => 'json']);

        if (! $response->successful()) {
            throw new RuntimeException('Wikidata query failed with HTTP '.$response->status().'.');
        }

        $facts = collect($response->json('results.bindings', []))
            ->map(fn (array $row) => $this->fact($row))
            ->filter()
            ->unique('element_url')
            ->values();

        if ($facts->count() < 4) {
            throw new RuntimeException('At least four complete bilingual chemical-element facts are required.');
        }

        $subject = Subject::where('name', 'Chemistry')->firstOrFail();
        $topic = Topic::where('subject_id', $subject->id)->where('name', 'Periodic Table')->firstOrFail();
        $examIds = $subject->exams()->where('is_active', true)->pluck('exams.id')->all();

        $questions = $facts->flatMap(function (array $fact) use ($facts, $subject, $topic, $examIds): array {
            return [
                $this->question(
                    $fact,
                    $facts,
                    'symbol',
                    "What is the chemical symbol of {$fact['element_en']}?",
                    "{$fact['element_hi']} का रासायनिक प्रतीक क्या है?",
                    "The chemical symbol of {$fact['element_en']} is {$fact['symbol']}.",
                    "{$fact['element_hi']} का रासायनिक प्रतीक {$fact['symbol']} है।",
                    'wikidata-element-symbol',
                    $subject->id,
                    $topic->id,
                    $examIds,
                ),
                $this->question(
                    $fact,
                    $facts,
                    'atomic_number',
                    "What is the atomic number of {$fact['element_en']}?",
                    "{$fact['element_hi']} की परमाणु संख्या क्या है?",
                    "The atomic number of {$fact['element_en']} is {$fact['atomic_number']}.",
                    "{$fact['element_hi']} की परमाणु संख्या {$fact['atomic_number']} है।",
                    'wikidata-element-atomic-number',
                    $subject->id,
                    $topic->id,
                    $examIds,
                ),
            ];
        })->all();

        if ($dryRun) {
            return ['facts' => $facts->count(), 'fetched' => count($questions), 'accepted' => count($questions), 'duplicates' => 0, 'rejected' => 0, 'dry_run' => true];
        }

        $batch = $this->ingestion->ingest($questions, $source, 'json');

        return [
            'facts' => $facts->count(),
            'fetched' => count($questions),
            'accepted' => $batch->accepted_count,
            'duplicates' => $batch->duplicate_count,
            'rejected' => $batch->rejected_count,
            'dry_run' => false,
        ];
    }

    private function question(
        array $fact,
        Collection $facts,
        string $answerKey,
        string $questionEn,
        string $questionHi,
        string $explanationEn,
        string $explanationHi,
        string $reference,
        int $subjectId,
        int $topicId,
        array $examIds,
    ): array {
        $answer = (string) $fact[$answerKey];
        $options = $facts
            ->reject(fn (array $candidate) => (string) $candidate[$answerKey] === $answer)
            ->sortBy(fn (array $candidate) => hash('sha256', $fact['element_url'].'|'.$answerKey.'|'.$candidate[$answerKey]))
            ->take(3)
            ->push($fact)
            ->sortBy(fn (array $candidate) => hash('sha256', $fact['element_url'].'|option|'.$answerKey.'|'.$candidate[$answerKey]))
            ->values()
            ->map(fn (array $candidate) => [
                'option_text' => (string) $candidate[$answerKey],
                'option_text_hi' => (string) $candidate[$answerKey],
                'is_correct' => (string) $candidate[$answerKey] === $answer,
            ])->all();

        return [
            'question_text' => $questionEn,
            'question_text_hi' => $questionHi,
            'explanation' => $explanationEn,
            'explanation_hi' => $explanationHi,
            'subject_id' => $subjectId,
            'topic_id' => $topicId,
            'exam_ids' => $examIds,
            'difficulty' => 'easy',
            'language' => 'bilingual',
            'source_url' => $fact['element_url'],
            'source_reference' => $reference,
            'source_published_at' => now()->toDateString(),
            'generation_method' => 'automated',
            'options' => $options,
        ];
    }

    private function fact(array $row): ?array
    {
        $atomicNumber = data_get($row, 'atomicNumber.value');
        $fact = [
            'element_url' => data_get($row, 'element.value'),
            'element_en' => data_get($row, 'elementLabelEn.value'),
            'element_hi' => data_get($row, 'elementLabelHi.value'),
            'symbol' => data_get($row, 'symbol.value'),
            'atomic_number' => is_numeric($atomicNumber) ? (string) (int) $atomicNumber : null,
        ];

        return collect($fact)->every(fn ($value) => is_string($value) && trim($value) !== '') ? $fact : null;
    }

    private function query(int $limit): string
    {
        return <<<SPARQL
SELECT DISTINCT ?element ?elementLabelEn ?elementLabelHi ?symbol ?atomicNumber WHERE {
  ?element wdt:P31 wd:Q11344;
           wdt:P246 ?symbol;
           wdt:P1086 ?atomicNumber;
           rdfs:label ?elementLabelEn;
           rdfs:label ?elementLabelHi.
  FILTER(LANG(?elementLabelEn) = "en")
  FILTER(LANG(?elementLabelHi) = "hi")
}
ORDER BY xsd:integer(?atomicNumber)
LIMIT {$limit}
SPARQL;
    }
}
