<?php

namespace App\Services;

use App\Models\ContentSource;
use App\Models\Subject;
use App\Models\Topic;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class WikidataSoftwareImporter
{
    private const ENDPOINT = 'https://query.wikidata.org/sparql';

    public function __construct(
        private readonly QuestionIngestionService $ingestion,
        private readonly TrustedSourcePolicy $sourcePolicy,
        private readonly TrustedSourceHealthService $health,
    ) {}

    public function import(int $limit = 500, bool $dryRun = false): array
    {
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
            ->get(self::ENDPOINT, ['query' => $this->query($limit), 'format' => 'json']);

        if (! $response->successful()) {
            throw new RuntimeException('Wikidata query failed with HTTP '.$response->status().'.');
        }

        $facts = collect($response->json('results.bindings', []))
            ->map(fn (array $row) => $this->fact($row))
            ->filter()
            ->groupBy('software_url')
            ->filter(fn (Collection $rows) => $rows->unique('developer_url')->count() === 1)
            ->map(fn (Collection $rows) => $rows->first())
            ->values();
        $developers = $facts->unique('developer_url')->values();

        if ($facts->count() < 4 || $developers->count() < 4) {
            throw new RuntimeException('At least four unambiguous bilingual software facts and developers are required.');
        }

        $subject = Subject::where('name', 'Computer Knowledge')->firstOrFail();
        $topic = Topic::where('subject_id', $subject->id)->where('name', 'Software')->firstOrFail();
        $examIds = $subject->exams()->where('is_active', true)->pluck('exams.id')->all();

        $questions = $facts->map(function (array $fact) use ($developers, $subject, $topic, $examIds): array {
            $options = $developers
                ->reject(fn (array $candidate) => $candidate['developer_url'] === $fact['developer_url'])
                ->sortBy(fn (array $candidate) => hash('sha256', $fact['software_url'].'|'.$candidate['developer_url']))
                ->take(3)
                ->push($fact)
                ->sortBy(fn (array $candidate) => hash('sha256', $fact['software_url'].'|option|'.$candidate['developer_url']))
                ->values()
                ->map(fn (array $candidate) => [
                    'option_text' => $candidate['developer_en'],
                    'option_text_hi' => $candidate['developer_hi'],
                    'is_correct' => $candidate['developer_url'] === $fact['developer_url'],
                ])->all();

            return [
                'question_text' => "Who developed {$fact['software_en']}?",
                'question_text_hi' => "{$fact['software_hi']} को किसने विकसित किया?",
                'explanation' => "{$fact['software_en']} was developed by {$fact['developer_en']}.",
                'explanation_hi' => "{$fact['software_hi']} को {$fact['developer_hi']} ने विकसित किया।",
                'subject_id' => $subject->id,
                'topic_id' => $topic->id,
                'exam_ids' => $examIds,
                'difficulty' => 'medium',
                'language' => 'bilingual',
                'source_url' => $fact['software_url'],
                'source_reference' => 'wikidata-software-developer',
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
            'software_url' => data_get($row, 'software.value'),
            'software_en' => data_get($row, 'softwareLabelEn.value'),
            'software_hi' => data_get($row, 'softwareLabelHi.value'),
            'developer_url' => data_get($row, 'developer.value'),
            'developer_en' => data_get($row, 'developerLabelEn.value'),
            'developer_hi' => data_get($row, 'developerLabelHi.value'),
        ];

        return collect($fact)->every(fn ($value) => is_string($value) && trim($value) !== '') ? $fact : null;
    }

    private function query(int $limit): string
    {
        return <<<SPARQL
SELECT DISTINCT ?software ?softwareLabelEn ?softwareLabelHi ?developer ?developerLabelEn ?developerLabelHi WHERE {
  ?software wdt:P178 ?developer;
            wdt:P31/wdt:P279* wd:Q7397;
            rdfs:label ?softwareLabelEn;
            rdfs:label ?softwareLabelHi.
  ?developer rdfs:label ?developerLabelEn;
             rdfs:label ?developerLabelHi.
  FILTER(LANG(?softwareLabelEn) = "en")
  FILTER(LANG(?softwareLabelHi) = "hi")
  FILTER(LANG(?developerLabelEn) = "en")
  FILTER(LANG(?developerLabelHi) = "hi")
}
ORDER BY ?softwareLabelEn ?developerLabelEn
LIMIT {$limit}
SPARQL;
    }
}
