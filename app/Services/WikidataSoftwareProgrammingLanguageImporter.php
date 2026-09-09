<?php

namespace App\Services;

use App\Models\ContentSource;
use App\Models\Subject;
use App\Models\Topic;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class WikidataSoftwareProgrammingLanguageImporter
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
            ->groupBy('software_url')->filter(fn (Collection $rows) => $rows->unique('language_url')->count() === 1)
            ->map(fn (Collection $rows) => $rows->first())->values();
        $languages = $facts->unique('language_url')->values();
        if ($facts->count() < 4 || $languages->count() < 4) {
            throw new RuntimeException('At least four unambiguous bilingual software titles and distinct programming languages are required.');
        }

        $subject = Subject::where('name', 'Computer Knowledge')->firstOrFail();
        $topic = Topic::where('subject_id', $subject->id)->where('name', 'Software')->firstOrFail();
        $examIds = $subject->exams()->where('is_active', true)->pluck('exams.id')->all();
        $questions = $facts->map(function (array $fact) use ($languages, $subject, $topic, $examIds): array {
            $options = $languages->reject(fn (array $candidate) => $candidate['language_url'] === $fact['language_url'])
                ->sortBy(fn (array $candidate) => hash('sha256', $fact['software_url'].'|'.$candidate['language_url']))->take(3)->push($fact)
                ->sortBy(fn (array $candidate) => hash('sha256', $fact['software_url'].'|option|'.$candidate['language_url']))->values()
                ->map(fn (array $candidate) => [
                    'option_text' => $candidate['language_en'],
                    'option_text_hi' => $candidate['language_hi'],
                    'is_correct' => $candidate['language_url'] === $fact['language_url'],
                ])->all();

            return [
                'question_text' => "Which programming language is {$fact['software_en']} primarily implemented in?",
                'question_text_hi' => "{$fact['software_hi']} मुख्य रूप से किस प्रोग्रामिंग भाषा में विकसित किया गया है?",
                'explanation' => "{$fact['software_en']} is primarily implemented in {$fact['language_en']}.",
                'explanation_hi' => "{$fact['software_hi']} मुख्य रूप से {$fact['language_hi']} में विकसित किया गया है।",
                'subject_id' => $subject->id, 'topic_id' => $topic->id, 'exam_ids' => $examIds,
                'difficulty' => 'medium', 'language' => 'bilingual', 'source_url' => $fact['software_url'],
                'source_reference' => 'wikidata-software-programming-language', 'source_published_at' => now()->toDateString(),
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
        $fact = ['software_url' => data_get($row, 'software.value'), 'software_en' => data_get($row, 'softwareLabelEn.value'),
            'software_hi' => data_get($row, 'softwareLabelHi.value'), 'language_url' => data_get($row, 'language.value'),
            'language_en' => data_get($row, 'languageLabelEn.value'), 'language_hi' => data_get($row, 'languageLabelHi.value')];

        return collect($fact)->every(fn ($value) => is_string($value) && trim($value) !== '') ? $fact : null;
    }

    private function query(int $limit): string
    {
        return <<<SPARQL
SELECT DISTINCT ?software ?softwareLabelEn ?softwareLabelHi ?language ?languageLabelEn ?languageLabelHi WHERE {
  ?software wdt:P277 ?language;
            wdt:P31/wdt:P279* wd:Q7397;
            rdfs:label ?softwareLabelEn;
            rdfs:label ?softwareLabelHi.
  ?language rdfs:label ?languageLabelEn; rdfs:label ?languageLabelHi.
  FILTER(LANG(?softwareLabelEn) = "en")
  FILTER(LANG(?softwareLabelHi) = "hi")
  FILTER(LANG(?languageLabelEn) = "en")
  FILTER(LANG(?languageLabelHi) = "hi")
}
ORDER BY ?softwareLabelEn ?languageLabelEn
LIMIT {$limit}
SPARQL;
    }
}
