<?php

namespace App\Services;

use App\Models\ContentSource;
use App\Models\Subject;
use App\Models\Topic;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class WikidataOrganizationInceptionYearImporter
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
            ->timeout(45)->retry(2, 750, throw: false)
            ->get(self::ENDPOINT, ['query' => $this->query($limit), 'format' => 'json']);

        if (! $response->successful()) {
            throw new RuntimeException('Wikidata query failed with HTTP '.$response->status().'.');
        }

        $facts = collect($response->json('results.bindings', []))
            ->map(fn (array $row) => $this->fact($row))->filter()
            ->groupBy('organization_url')
            ->filter(fn (Collection $rows) => $rows->unique('year')->count() === 1)
            ->map(fn (Collection $rows) => $rows->first())->values();
        $years = $facts->unique('year')->values();

        if ($facts->count() < 4 || $years->count() < 4) {
            throw new RuntimeException('At least four unambiguous bilingual organizations and distinct inception years are required.');
        }

        $subject = Subject::where('name', 'General Knowledge')->firstOrFail();
        $topic = Topic::where('subject_id', $subject->id)->where('name', 'Organizations')->firstOrFail();
        $examIds = $subject->exams()->where('is_active', true)->pluck('exams.id')->all();

        $questions = $facts->map(function (array $fact) use ($years, $subject, $topic, $examIds): array {
            $options = $years
                ->reject(fn (array $candidate) => $candidate['year'] === $fact['year'])
                ->sortBy(fn (array $candidate) => hash('sha256', $fact['organization_url'].'|'.$candidate['year']))
                ->take(3)->push($fact)
                ->sortBy(fn (array $candidate) => hash('sha256', $fact['organization_url'].'|option|'.$candidate['year']))
                ->values()->map(fn (array $candidate) => [
                    'option_text' => (string) $candidate['year'],
                    'option_text_hi' => $this->toHindiDigits((string) $candidate['year']),
                    'is_correct' => $candidate['year'] === $fact['year'],
                ])->all();

            return [
                'question_text' => "In which year was {$fact['organization_en']} established?",
                'question_text_hi' => "{$fact['organization_hi']} की स्थापना किस वर्ष हुई थी?",
                'explanation' => "{$fact['organization_en']} was established in {$fact['year']}.",
                'explanation_hi' => "{$fact['organization_hi']} की स्थापना {$this->toHindiDigits((string) $fact['year'])} में हुई थी।",
                'subject_id' => $subject->id,
                'topic_id' => $topic->id,
                'exam_ids' => $examIds,
                'difficulty' => 'medium',
                'language' => 'bilingual',
                'source_url' => $fact['organization_url'],
                'source_reference' => 'wikidata-international-organization-inception-year',
                'source_published_at' => now()->toDateString(),
                'generation_method' => 'automated',
                'options' => $options,
            ];
        })->all();

        if ($dryRun) {
            return ['fetched' => count($questions), 'accepted' => count($questions), 'duplicates' => 0, 'rejected' => 0, 'dry_run' => true];
        }

        $batch = $this->ingestion->ingest($questions, $source, 'json');

        return ['fetched' => count($questions), 'accepted' => $batch->accepted_count,
            'duplicates' => $batch->duplicate_count, 'rejected' => $batch->rejected_count, 'dry_run' => false];
    }

    private function fact(array $row): ?array
    {
        $url = data_get($row, 'organization.value');
        $en = data_get($row, 'organizationLabelEn.value');
        $hi = data_get($row, 'organizationLabelHi.value');
        $date = data_get($row, 'inception.value');

        if (! collect([$url, $en, $hi, $date])->every(fn ($value) => is_string($value) && trim($value) !== '')) {
            return null;
        }

        $year = (int) substr($date, 0, 4);

        return $year >= 1000 && $year <= (int) now()->format('Y')
            ? ['organization_url' => $url, 'organization_en' => $en, 'organization_hi' => $hi, 'year' => $year]
            : null;
    }

    private function toHindiDigits(string $value): string
    {
        return strtr($value, ['0'=>'०','1'=>'१','2'=>'२','3'=>'३','4'=>'४','5'=>'५','6'=>'६','7'=>'७','8'=>'८','9'=>'९']);
    }

    private function query(int $limit): string
    {
        return <<<SPARQL
SELECT DISTINCT ?organization ?organizationLabelEn ?organizationLabelHi ?inception WHERE {
  ?organization wdt:P571 ?inception;
                wdt:P31/wdt:P279* wd:Q484652;
                rdfs:label ?organizationLabelEn;
                rdfs:label ?organizationLabelHi.
  FILTER(LANG(?organizationLabelEn) = "en")
  FILTER(LANG(?organizationLabelHi) = "hi")
  FILTER(DATATYPE(?inception) = xsd:dateTime)
}
ORDER BY ?organizationLabelEn ?inception
LIMIT {$limit}
SPARQL;
    }
}
