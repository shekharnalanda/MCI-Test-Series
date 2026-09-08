<?php

namespace App\Services;

use App\Models\ContentSource;
use App\Models\Subject;
use App\Models\Topic;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class WikidataProgrammingLanguageImporter
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
            ->groupBy('language_url')
            ->filter(fn (Collection $rows) => $rows->unique('designer_url')->count() === 1)
            ->map(fn (Collection $rows) => $rows->first())
            ->values();
        $designers = $facts->unique('designer_url')->values();

        if ($facts->count() < 4 || $designers->count() < 4) {
            throw new RuntimeException('At least four unambiguous bilingual programming-language facts and designers are required.');
        }

        $subject = Subject::where('name', 'Computer Knowledge')->firstOrFail();
        $topic = Topic::where('subject_id', $subject->id)->where('name', 'Software')->firstOrFail();
        $examIds = $subject->exams()->where('is_active', true)->pluck('exams.id')->all();

        $questions = $facts->map(function (array $fact) use ($designers, $subject, $topic, $examIds): array {
            $options = $designers
                ->reject(fn (array $candidate) => $candidate['designer_url'] === $fact['designer_url'])
                ->sortBy(fn (array $candidate) => hash('sha256', $fact['language_url'].'|'.$candidate['designer_url']))
                ->take(3)
                ->push($fact)
                ->sortBy(fn (array $candidate) => hash('sha256', $fact['language_url'].'|option|'.$candidate['designer_url']))
                ->values()
                ->map(fn (array $candidate) => [
                    'option_text' => $candidate['designer_en'],
                    'option_text_hi' => $candidate['designer_hi'],
                    'is_correct' => $candidate['designer_url'] === $fact['designer_url'],
                ])->all();

            return [
                'question_text' => "Who designed the {$fact['language_en']} programming language?",
                'question_text_hi' => "{$fact['language_hi']} प्रोग्रामिंग भाषा को किसने डिजाइन किया?",
                'explanation' => "The {$fact['language_en']} programming language was designed by {$fact['designer_en']}.",
                'explanation_hi' => "{$fact['language_hi']} प्रोग्रामिंग भाषा को {$fact['designer_hi']} ने डिजाइन किया।",
                'subject_id' => $subject->id,
                'topic_id' => $topic->id,
                'exam_ids' => $examIds,
                'difficulty' => 'medium',
                'language' => 'bilingual',
                'source_url' => $fact['language_url'],
                'source_reference' => 'wikidata-programming-language-designer',
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
            'language_url' => data_get($row, 'language.value'),
            'language_en' => data_get($row, 'languageLabelEn.value'),
            'language_hi' => data_get($row, 'languageLabelHi.value'),
            'designer_url' => data_get($row, 'designer.value'),
            'designer_en' => data_get($row, 'designerLabelEn.value'),
            'designer_hi' => data_get($row, 'designerLabelHi.value'),
        ];

        return collect($fact)->every(fn ($value) => is_string($value) && trim($value) !== '') ? $fact : null;
    }

    private function query(int $limit): string
    {
        return <<<SPARQL
SELECT DISTINCT ?language ?languageLabelEn ?languageLabelHi ?designer ?designerLabelEn ?designerLabelHi WHERE {
  ?language wdt:P287 ?designer;
            wdt:P31/wdt:P279* wd:Q9143;
            rdfs:label ?languageLabelEn;
            rdfs:label ?languageLabelHi.
  ?designer rdfs:label ?designerLabelEn;
            rdfs:label ?designerLabelHi.
  FILTER(LANG(?languageLabelEn) = "en")
  FILTER(LANG(?languageLabelHi) = "hi")
  FILTER(LANG(?designerLabelEn) = "en")
  FILTER(LANG(?designerLabelHi) = "hi")
}
ORDER BY ?languageLabelEn ?designerLabelEn
LIMIT {$limit}
SPARQL;
    }
}
