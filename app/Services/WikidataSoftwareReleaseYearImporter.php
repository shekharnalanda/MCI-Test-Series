<?php

namespace App\Services;

use App\Models\ContentSource;
use App\Models\Subject;
use App\Models\Topic;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class WikidataSoftwareReleaseYearImporter
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
            ->groupBy('software_url')
            ->filter(fn (Collection $rows) => $rows->unique('year')->count() === 1)
            ->map(fn (Collection $rows) => $rows->first())->values();
        $years = $facts->unique('year')->values();

        if ($facts->count() < 4 || $years->count() < 4) {
            throw new RuntimeException('At least four unambiguous bilingual software titles and distinct release years are required.');
        }

        $subject = Subject::where('name', 'Computer Knowledge')->firstOrFail();
        $topic = Topic::where('subject_id', $subject->id)->where('name', 'Software')->firstOrFail();
        $examIds = $subject->exams()->where('is_active', true)->pluck('exams.id')->all();

        $questions = $facts->map(function (array $fact) use ($years, $subject, $topic, $examIds): array {
            $options = $years
                ->reject(fn (array $candidate) => $candidate['year'] === $fact['year'])
                ->sortBy(fn (array $candidate) => hash('sha256', $fact['software_url'].'|'.$candidate['year']))
                ->take(3)->push($fact)
                ->sortBy(fn (array $candidate) => hash('sha256', $fact['software_url'].'|option|'.$candidate['year']))
                ->values()->map(fn (array $candidate) => [
                    'option_text' => (string) $candidate['year'],
                    'option_text_hi' => $this->toHindiDigits((string) $candidate['year']),
                    'is_correct' => $candidate['year'] === $fact['year'],
                ])->all();

            return [
                'question_text' => "In which year was {$fact['software_en']} first released?",
                'question_text_hi' => "{$fact['software_hi']} पहली बार किस वर्ष जारी किया गया था?",
                'explanation' => "{$fact['software_en']} was first released in {$fact['year']}.",
                'explanation_hi' => "{$fact['software_hi']} पहली बार {$this->toHindiDigits((string) $fact['year'])} में जारी किया गया था।",
                'subject_id' => $subject->id, 'topic_id' => $topic->id, 'exam_ids' => $examIds,
                'difficulty' => 'medium', 'language' => 'bilingual',
                'source_url' => $fact['software_url'],
                'source_reference' => 'wikidata-software-first-release-year',
                'source_published_at' => now()->toDateString(), 'generation_method' => 'automated',
                'options' => $options,
            ];
        })->all();

        if ($dryRun) {
            return ['fetched'=>count($questions), 'accepted'=>count($questions), 'duplicates'=>0, 'rejected'=>0, 'dry_run'=>true];
        }

        $batch = $this->ingestion->ingest($questions, $source, 'json');

        return ['fetched'=>count($questions), 'accepted'=>$batch->accepted_count,
            'duplicates'=>$batch->duplicate_count, 'rejected'=>$batch->rejected_count, 'dry_run'=>false];
    }

    private function fact(array $row): ?array
    {
        $url = data_get($row, 'software.value');
        $en = data_get($row, 'softwareLabelEn.value');
        $hi = data_get($row, 'softwareLabelHi.value');
        $date = data_get($row, 'release.value');

        if (! collect([$url, $en, $hi, $date])->every(fn ($value) => is_string($value) && trim($value) !== '')) {
            return null;
        }

        $year = (int) substr($date, 0, 4);

        return $year >= 1940 && $year <= (int) now()->format('Y')
            ? ['software_url'=>$url, 'software_en'=>$en, 'software_hi'=>$hi, 'year'=>$year]
            : null;
    }

    private function toHindiDigits(string $value): string
    {
        return strtr($value, ['0'=>'०','1'=>'१','2'=>'२','3'=>'३','4'=>'४','5'=>'५','6'=>'६','7'=>'७','8'=>'८','9'=>'९']);
    }

    private function query(int $limit): string
    {
        return <<<SPARQL
SELECT DISTINCT ?software ?softwareLabelEn ?softwareLabelHi ?release WHERE {
  ?software wdt:P577 ?release;
            wdt:P31/wdt:P279* wd:Q7397;
            rdfs:label ?softwareLabelEn;
            rdfs:label ?softwareLabelHi.
  FILTER(LANG(?softwareLabelEn) = "en")
  FILTER(LANG(?softwareLabelHi) = "hi")
  FILTER(DATATYPE(?release) = xsd:dateTime)
}
ORDER BY ?softwareLabelEn ?release
LIMIT {$limit}
SPARQL;
    }
}
