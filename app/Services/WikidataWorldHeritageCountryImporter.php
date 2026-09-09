<?php

namespace App\Services;

use App\Models\ContentSource;
use App\Models\Subject;
use App\Models\Topic;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class WikidataWorldHeritageCountryImporter
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

        $facts = collect($response->json('results.bindings', []))->map(fn (array $row) => $this->fact($row))->filter()
            ->groupBy('site_url')->filter(fn (Collection $rows) => $rows->unique('country_url')->count() === 1)
            ->map(fn (Collection $rows) => $rows->first())->values();
        $countries = $facts->unique('country_url')->values();
        if ($facts->count() < 4 || $countries->count() < 4) {
            throw new RuntimeException('At least four unambiguous bilingual World Heritage sites and distinct countries are required.');
        }

        $subject = Subject::where('name', 'General Knowledge')->firstOrFail();
        $topic = Topic::where('subject_id', $subject->id)->where('name', 'Important Places')->firstOrFail();
        $examIds = $subject->exams()->where('is_active', true)->pluck('exams.id')->all();
        $questions = $facts->map(function (array $fact) use ($countries, $subject, $topic, $examIds): array {
            $options = $countries->reject(fn (array $candidate) => $candidate['country_url'] === $fact['country_url'])
                ->sortBy(fn (array $candidate) => hash('sha256', $fact['site_url'].'|'.$candidate['country_url']))->take(3)->push($fact)
                ->sortBy(fn (array $candidate) => hash('sha256', $fact['site_url'].'|option|'.$candidate['country_url']))->values()
                ->map(fn (array $candidate) => [
                    'option_text' => $candidate['country_en'],
                    'option_text_hi' => $candidate['country_hi'],
                    'is_correct' => $candidate['country_url'] === $fact['country_url'],
                ])->all();

            return [
                'question_text' => "In which country is the UNESCO World Heritage Site {$fact['site_en']} located?",
                'question_text_hi' => "यूनेस्को विश्व धरोहर स्थल {$fact['site_hi']} किस देश में स्थित है?",
                'explanation' => "{$fact['site_en']} is a UNESCO World Heritage Site located in {$fact['country_en']}.",
                'explanation_hi' => "{$fact['site_hi']} {$fact['country_hi']} में स्थित यूनेस्को विश्व धरोहर स्थल है।",
                'subject_id' => $subject->id, 'topic_id' => $topic->id, 'exam_ids' => $examIds,
                'difficulty' => 'medium', 'language' => 'bilingual', 'source_url' => $fact['site_url'],
                'source_reference' => 'wikidata-world-heritage-country', 'source_published_at' => now()->toDateString(),
                'generation_method' => 'automated', 'options' => $options,
            ];
        })->all();

        if ($dryRun) {
            return ['fetched' => count($questions), 'accepted' => count($questions), 'duplicates' => 0, 'rejected' => 0, 'dry_run' => true];
        }
        $batch = $this->ingestion->ingest($questions, $source, 'json');

        return ['fetched' => count($questions), 'accepted' => $batch->accepted_count, 'duplicates' => $batch->duplicate_count,
            'rejected' => $batch->rejected_count, 'dry_run' => false];
    }

    private function fact(array $row): ?array
    {
        $fact = ['site_url' => data_get($row, 'site.value'), 'site_en' => data_get($row, 'siteLabelEn.value'),
            'site_hi' => data_get($row, 'siteLabelHi.value'), 'country_url' => data_get($row, 'country.value'),
            'country_en' => data_get($row, 'countryLabelEn.value'), 'country_hi' => data_get($row, 'countryLabelHi.value')];

        return collect($fact)->every(fn ($value) => is_string($value) && trim($value) !== '') ? $fact : null;
    }

    private function query(int $limit): string
    {
        return <<<SPARQL
SELECT DISTINCT ?site ?siteLabelEn ?siteLabelHi ?country ?countryLabelEn ?countryLabelHi WHERE {
  ?site wdt:P1435 wd:Q9259;
        wdt:P17 ?country;
        rdfs:label ?siteLabelEn;
        rdfs:label ?siteLabelHi.
  ?country rdfs:label ?countryLabelEn;
           rdfs:label ?countryLabelHi.
  FILTER(LANG(?siteLabelEn) = "en")
  FILTER(LANG(?siteLabelHi) = "hi")
  FILTER(LANG(?countryLabelEn) = "en")
  FILTER(LANG(?countryLabelHi) = "hi")
}
ORDER BY ?siteLabelEn ?countryLabelEn
LIMIT {$limit}
SPARQL;
    }
}
