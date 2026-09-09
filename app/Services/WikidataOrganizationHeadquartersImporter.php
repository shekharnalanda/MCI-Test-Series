<?php

namespace App\Services;

use App\Models\ContentSource;
use App\Models\Subject;
use App\Models\Topic;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class WikidataOrganizationHeadquartersImporter
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
            ->groupBy('organization_url')
            ->filter(fn (Collection $rows) => $rows->unique('headquarters_url')->count() === 1)
            ->map(fn (Collection $rows) => $rows->first())
            ->values();
        $headquarters = $facts->unique('headquarters_url')->values();

        if ($facts->count() < 4 || $headquarters->count() < 4) {
            throw new RuntimeException('At least four unambiguous bilingual organizations and headquarters are required.');
        }

        $subject = Subject::where('name', 'General Knowledge')->firstOrFail();
        $topic = Topic::where('subject_id', $subject->id)->where('name', 'Organizations')->firstOrFail();
        $examIds = $subject->exams()->where('is_active', true)->pluck('exams.id')->all();

        $questions = $facts->map(function (array $fact) use ($headquarters, $subject, $topic, $examIds): array {
            $options = $headquarters
                ->reject(fn (array $candidate) => $candidate['headquarters_url'] === $fact['headquarters_url'])
                ->sortBy(fn (array $candidate) => hash('sha256', $fact['organization_url'].'|'.$candidate['headquarters_url']))
                ->take(3)
                ->push($fact)
                ->sortBy(fn (array $candidate) => hash('sha256', $fact['organization_url'].'|option|'.$candidate['headquarters_url']))
                ->values()
                ->map(fn (array $candidate) => [
                    'option_text' => $candidate['headquarters_en'],
                    'option_text_hi' => $candidate['headquarters_hi'],
                    'is_correct' => $candidate['headquarters_url'] === $fact['headquarters_url'],
                ])->all();

            return [
                'question_text' => "Where is the headquarters of {$fact['organization_en']}?",
                'question_text_hi' => "{$fact['organization_hi']} का मुख्यालय कहाँ स्थित है?",
                'explanation' => "The headquarters of {$fact['organization_en']} is in {$fact['headquarters_en']}.",
                'explanation_hi' => "{$fact['organization_hi']} का मुख्यालय {$fact['headquarters_hi']} में स्थित है।",
                'subject_id' => $subject->id,
                'topic_id' => $topic->id,
                'exam_ids' => $examIds,
                'difficulty' => 'medium',
                'language' => 'bilingual',
                'source_url' => $fact['organization_url'],
                'source_reference' => 'wikidata-international-organization-headquarters',
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
            'organization_url' => data_get($row, 'organization.value'),
            'organization_en' => data_get($row, 'organizationLabelEn.value'),
            'organization_hi' => data_get($row, 'organizationLabelHi.value'),
            'headquarters_url' => data_get($row, 'headquarters.value'),
            'headquarters_en' => data_get($row, 'headquartersLabelEn.value'),
            'headquarters_hi' => data_get($row, 'headquartersLabelHi.value'),
        ];

        return collect($fact)->every(fn ($value) => is_string($value) && trim($value) !== '') ? $fact : null;
    }

    private function query(int $limit): string
    {
        return <<<SPARQL
SELECT DISTINCT ?organization ?organizationLabelEn ?organizationLabelHi ?headquarters ?headquartersLabelEn ?headquartersLabelHi WHERE {
  ?organization wdt:P159 ?headquarters;
                wdt:P31/wdt:P279* wd:Q484652;
                rdfs:label ?organizationLabelEn;
                rdfs:label ?organizationLabelHi.
  ?headquarters rdfs:label ?headquartersLabelEn;
                rdfs:label ?headquartersLabelHi.
  FILTER(LANG(?organizationLabelEn) = "en")
  FILTER(LANG(?organizationLabelHi) = "hi")
  FILTER(LANG(?headquartersLabelEn) = "en")
  FILTER(LANG(?headquartersLabelHi) = "hi")
}
ORDER BY ?organizationLabelEn ?headquartersLabelEn
LIMIT {$limit}
SPARQL;
    }
}
