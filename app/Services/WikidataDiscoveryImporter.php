<?php

namespace App\Services;

use App\Models\ContentSource;
use App\Models\Subject;
use App\Models\Topic;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class WikidataDiscoveryImporter
{
    private const ENDPOINT = 'https://query.wikidata.org/sparql';

    public function __construct(
        private readonly QuestionIngestionService $ingestion,
        private readonly TrustedSourcePolicy $sourcePolicy,
        private readonly TrustedSourceHealthService $health,
    ) {}

    public function import(int $limit = 500, bool $dryRun = false, int $offset = 0): array
    {
        $limit = max(10, min($limit, 500));
        $offset = max(0, $offset);
        $source = ContentSource::where('slug', 'wikidata')->where('is_active', true)->firstOrFail();
        $this->health->check($source);
        $source->refresh();

        if (! $this->sourcePolicy->canGenerateQuestions($source)) {
            throw new RuntimeException('Wikidata has not passed the trusted-source policy.');
        }

        $response = Http::withHeaders(['Accept' => 'application/sparql-results+json'])
            ->withUserAgent('MCI-Test-Series/1.0 (+https://test.mciedu.com)')
            ->connectTimeout(5)
            ->timeout(25)
            ->retry(2, 750, throw: false)
            ->get(self::ENDPOINT, ['query' => $this->query($limit, $offset), 'format' => 'json']);

        if (! $response->successful()) {
            throw new RuntimeException('Wikidata query failed with HTTP '.$response->status().'.');
        }

        $rows = $response->json('results.bindings');
        if (! is_array($rows)) {
            throw new RuntimeException('Wikidata returned an invalid result; cursor was not advanced.');
        }
        $page = ['source_rows' => count($rows), 'next_offset' => count($rows) < $limit ? 0 : $offset + count($rows)];
        $facts = collect($rows)
            ->map(fn (array $row) => $this->fact($row))
            ->filter()
            ->groupBy('item_url')
            ->filter(fn (Collection $rows) => $rows->unique('person_url')->count() === 1)
            ->map(fn (Collection $rows) => $rows->first())
            ->values();
        $people = $facts->unique('person_url')->values();

        if ($facts->count() < 4 || $people->count() < 4) {
            return ['fetched' => 0, 'accepted' => 0, 'duplicates' => 0, 'rejected' => count($rows), 'dry_run' => $dryRun] + $page;
        }

        $subject = Subject::where('name', 'General Science')->firstOrFail();
        $topic = Topic::where('subject_id', $subject->id)->where('name', 'Discoveries and Inventions')->firstOrFail();
        $examIds = $subject->exams()->where('is_active', true)->pluck('exams.id')->all();

        $questions = $facts->map(function (array $fact) use ($people, $subject, $topic, $examIds): array {
            $options = $people
                ->reject(fn (array $candidate) => $candidate['person_url'] === $fact['person_url'])
                ->sortBy(fn (array $candidate) => hash('sha256', $fact['item_url'].'|'.$candidate['person_url']))
                ->take(3)
                ->push($fact)
                ->sortBy(fn (array $candidate) => hash('sha256', $fact['item_url'].'|option|'.$candidate['person_url']))
                ->values()
                ->map(fn (array $candidate) => [
                    'option_text' => $candidate['person_en'],
                    'option_text_hi' => $candidate['person_hi'],
                    'is_correct' => $candidate['person_url'] === $fact['person_url'],
                ])->all();

            return [
                'question_text' => "Who is credited as the discoverer or inventor of {$fact['item_en']}?",
                'question_text_hi' => "{$fact['item_hi']} के खोजकर्ता या आविष्कारक के रूप में किसे श्रेय दिया जाता है?",
                'explanation' => "{$fact['person_en']} is credited as the discoverer or inventor of {$fact['item_en']}.",
                'explanation_hi' => "{$fact['item_hi']} के खोजकर्ता या आविष्कारक के रूप में {$fact['person_hi']} को श्रेय दिया जाता है।",
                'subject_id' => $subject->id,
                'topic_id' => $topic->id,
                'exam_ids' => $examIds,
                'difficulty' => 'medium',
                'language' => 'bilingual',
                'source_url' => $fact['item_url'],
                'source_reference' => 'wikidata-discovery-inventor',
                'source_published_at' => now()->toDateString(),
                'generation_method' => 'automated',
                'options' => $options,
            ];
        })->all();

        if ($dryRun) {
            return ['fetched' => count($questions), 'accepted' => count($questions), 'duplicates' => 0, 'rejected' => 0, 'dry_run' => true] + $page;
        }

        $batch = $this->ingestion->ingest($questions, $source, 'json');

        return [
            'fetched' => count($questions),
            'accepted' => $batch->accepted_count,
            'duplicates' => $batch->duplicate_count,
            'rejected' => $batch->rejected_count,
            'dry_run' => false,
        ] + $page;
    }

    private function fact(array $row): ?array
    {
        $fact = [
            'item_url' => data_get($row, 'item.value'),
            'item_en' => data_get($row, 'itemLabelEn.value'),
            'item_hi' => data_get($row, 'itemLabelHi.value'),
            'person_url' => data_get($row, 'person.value'),
            'person_en' => data_get($row, 'personLabelEn.value'),
            'person_hi' => data_get($row, 'personLabelHi.value'),
        ];

        return collect($fact)->every(fn ($value) => is_string($value) && trim($value) !== '') ? $fact : null;
    }

    private function query(int $limit, int $offset): string
    {
        return <<<SPARQL
SELECT DISTINCT ?item ?itemLabelEn ?itemLabelHi ?person ?personLabelEn ?personLabelHi WHERE {
  ?item wdt:P61 ?person;
        rdfs:label ?itemLabelEn;
        rdfs:label ?itemLabelHi.
  ?person wdt:P31 wd:Q5;
          rdfs:label ?personLabelEn;
          rdfs:label ?personLabelHi.
  FILTER NOT EXISTS { ?item wdt:P61 ?otherAnswer. FILTER(?otherAnswer != ?person) }
  FILTER(LANG(?itemLabelEn) = "en")
  FILTER(LANG(?itemLabelHi) = "hi")
  FILTER(LANG(?personLabelEn) = "en")
  FILTER(LANG(?personLabelHi) = "hi")
}
ORDER BY ?item ?person ?itemLabelEn ?personLabelEn
LIMIT {$limit}
OFFSET {$offset}
SPARQL;
    }
}
