<?php

namespace App\Services;

use App\Models\ContentSource;
use App\Models\Subject;
use App\Models\Topic;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class WikidataCountryCapitalMatchHardImporter
{
    private const ENDPOINT = 'https://query.wikidata.org/sparql';

    public function __construct(
        private readonly QuestionIngestionService $ingestion,
        private readonly TrustedSourcePolicy $sourcePolicy,
        private readonly TrustedSourceHealthService $health,
    ) {}

    public function import(int $limit = 15, bool $dryRun = false): array
    {
        $limit = max(1, min($limit, 25));
        $source = ContentSource::where('slug', 'wikidata')->where('is_active', true)->firstOrFail();
        $this->health->check($source);
        $source->refresh();

        if (! $this->sourcePolicy->canGenerateQuestions($source)) {
            throw new RuntimeException('Wikidata has not passed the trusted-source policy.');
        }

        $response = Http::withHeaders(['Accept' => 'application/sparql-results+json'])
            ->withUserAgent('MCI-Test-Series/1.0 (+https://test.mciedu.com)')
            ->timeout(45)->retry(2, 750, throw: false)
            ->get(self::ENDPOINT, ['query' => $this->query(), 'format' => 'json']);

        if (! $response->successful()) {
            throw new RuntimeException('Wikidata query failed with HTTP '.$response->status().'.');
        }

        $facts = collect($response->json('results.bindings', []))
            ->map(fn (array $row) => $this->fact($row))->filter()
            ->groupBy('country_id')
            ->filter(fn (Collection $rows) => $rows->unique('capital_id')->count() === 1)
            ->map(fn (Collection $rows) => $rows->first())->values();

        if ($facts->count() < 8) {
            throw new RuntimeException('At least eight unambiguous bilingual country-capital facts are required.');
        }

        $subject = Subject::where('name', 'Static GK')->firstOrFail();
        $topic = Topic::where('subject_id', $subject->id)
            ->where('name', 'Countries Capitals Currencies')->firstOrFail();
        $examIds = $subject->exams()->where('is_active', true)->pluck('exams.id')->all();

        $questions = $this->groups($facts, $limit)->map(fn (Collection $group) =>
            $this->payload($group, $subject->id, $topic->id, $examIds))->all();

        if ($dryRun) {
            return ['fetched' => count($questions), 'accepted' => count($questions), 'duplicates' => 0,
                'rejected' => 0, 'dry_run' => true];
        }

        $batch = $this->ingestion->ingest($questions, $source, 'json');

        return ['fetched' => count($questions), 'accepted' => $batch->accepted_count,
            'duplicates' => $batch->duplicate_count, 'rejected' => $batch->rejected_count, 'dry_run' => false];
    }

    private function groups(Collection $facts, int $limit): Collection
    {
        $count = $facts->count();

        return collect(range(0, $limit - 1))->map(function (int $index) use ($facts, $count) {
            return collect([$index, $index + 11, $index + 23, $index + 37])
                ->map(fn (int $position) => $facts[$position % $count])->unique('country_id')->values();
        })->filter(fn (Collection $group) => $group->count() === 4)
            ->unique(fn (Collection $group) => $group->pluck('country_id')->sort()->implode('|'))->values();
    }

    private function payload(Collection $group, int $subjectId, int $topicId, array $examIds): array
    {
        $countriesEn = $group->pluck('country_en')->implode(', ');
        $countriesHi = $group->pluck('country_hi')->implode(', ');
        $orders = collect([0, 1, 2, 3])->map(function (int $shift) use ($group) {
            return $group->values()->map(function (array $country, int $index) use ($group, $shift) {
                $capital = $group[($index + $shift) % 4];

                return [
                    'en' => $country['country_en'].' — '.$capital['capital_en'],
                    'hi' => $country['country_hi'].' — '.$capital['capital_hi'],
                ];
            });
        });
        $entities = $group->flatMap(fn (array $fact) => [$fact['country_id'], $fact['capital_id']])->unique()->sort();
        $explanationEn = $group->map(fn (array $fact) => $fact['country_en'].' — '.$fact['capital_en'])->implode('; ');
        $explanationHi = $group->map(fn (array $fact) => $fact['country_hi'].' — '.$fact['capital_hi'])->implode('; ');

        return [
            'question_text' => "Which option correctly matches all four countries—{$countriesEn}—with their capitals?",
            'question_text_hi' => "कौन-सा विकल्प {$countriesHi}—इन चारों देशों का उनकी राजधानियों से सही मिलान करता है?",
            'explanation' => "The correct matches are: {$explanationEn}.",
            'explanation_hi' => "सही मिलान हैं: {$explanationHi}।",
            'subject_id' => $subjectId, 'topic_id' => $topicId, 'exam_ids' => $examIds,
            'difficulty' => 'hard', 'language' => 'bilingual',
            'source_url' => 'https://www.wikidata.org/w/api.php?action=wbgetentities&format=json&ids='.
                $entities->implode('%7C'),
            'source_reference' => 'wikidata-country-capital-match:'.$group->pluck('country_id')->sort()->implode(','),
            'source_published_at' => now()->toDateString(), 'generation_method' => 'automated',
            'options' => $orders->map(fn (Collection $pairs, int $index) => [
                'option_text' => $pairs->pluck('en')->implode('; '),
                'option_text_hi' => $pairs->pluck('hi')->implode('; '),
                'is_correct' => $index === 0,
            ])->all(),
        ];
    }

    private function fact(array $row): ?array
    {
        $countryUrl = data_get($row, 'country.value');
        $capitalUrl = data_get($row, 'capital.value');
        $countryEn = data_get($row, 'countryLabelEn.value');
        $countryHi = data_get($row, 'countryLabelHi.value');
        $capitalEn = data_get($row, 'capitalLabelEn.value');
        $capitalHi = data_get($row, 'capitalLabelHi.value');

        if (! is_string($countryUrl) || ! preg_match('~/entity/(Q\\d+)$~', $countryUrl, $country)
            || ! is_string($capitalUrl) || ! preg_match('~/entity/(Q\\d+)$~', $capitalUrl, $capital)
            || ! collect([$countryEn, $countryHi, $capitalEn, $capitalHi])
                ->every(fn ($value) => is_string($value) && trim($value) !== '')) {
            return null;
        }

        return ['country_id' => $country[1], 'capital_id' => $capital[1], 'country_en' => trim($countryEn),
            'country_hi' => trim($countryHi), 'capital_en' => trim($capitalEn), 'capital_hi' => trim($capitalHi)];
    }

    private function query(): string
    {
        return <<<'SPARQL'
SELECT DISTINCT ?country ?countryLabelEn ?countryLabelHi ?capital ?capitalLabelEn ?capitalLabelHi WHERE {
  ?country wdt:P31 wd:Q3624078; wdt:P36 ?capital;
           rdfs:label ?countryLabelEn; rdfs:label ?countryLabelHi.
  ?capital rdfs:label ?capitalLabelEn; rdfs:label ?capitalLabelHi.
  FILTER NOT EXISTS { ?country wdt:P576 ?dissolvedDate. }
  FILTER(LANG(?countryLabelEn) = "en" && LANG(?countryLabelHi) = "hi")
  FILTER(LANG(?capitalLabelEn) = "en" && LANG(?capitalLabelHi) = "hi")
}
ORDER BY ?countryLabelEn
SPARQL;
    }
}
