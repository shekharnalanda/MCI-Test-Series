<?php

namespace App\Services;

use App\Models\ContentSource;
use App\Models\Subject;
use App\Models\Topic;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class WikidataBookAuthorImporter
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
            ->groupBy('book_url')
            ->filter(fn (Collection $rows) => $rows->unique('author_url')->count() === 1)
            ->map(fn (Collection $rows) => $rows->first())
            ->values();
        $authors = $facts->unique('author_url')->values();

        if ($facts->count() < 4 || $authors->count() < 4) {
            return ['fetched' => 0, 'accepted' => 0, 'duplicates' => 0, 'rejected' => count($rows), 'dry_run' => $dryRun] + $page;
        }

        $subject = Subject::where('name', 'General Knowledge')->firstOrFail();
        $topic = Topic::where('subject_id', $subject->id)->where('name', 'Books and Authors')->firstOrFail();
        $examIds = $subject->exams()->where('is_active', true)->pluck('exams.id')->all();

        $questions = $facts->map(function (array $fact) use ($authors, $subject, $topic, $examIds): array {
            $options = $authors
                ->reject(fn (array $candidate) => $candidate['author_url'] === $fact['author_url'])
                ->sortBy(fn (array $candidate) => hash('sha256', $fact['book_url'].'|'.$candidate['author_url']))
                ->take(3)
                ->push($fact)
                ->sortBy(fn (array $candidate) => hash('sha256', $fact['book_url'].'|option|'.$candidate['author_url']))
                ->values()
                ->map(fn (array $candidate) => [
                    'option_text' => $candidate['author_en'],
                    'option_text_hi' => $candidate['author_hi'],
                    'is_correct' => $candidate['author_url'] === $fact['author_url'],
                ])->all();

            return [
                'question_text' => "Who wrote {$fact['book_en']}?",
                'question_text_hi' => "{$fact['book_hi']} के लेखक कौन हैं?",
                'explanation' => "{$fact['book_en']} was written by {$fact['author_en']}.",
                'explanation_hi' => "{$fact['book_hi']} के लेखक {$fact['author_hi']} हैं।",
                'subject_id' => $subject->id,
                'topic_id' => $topic->id,
                'exam_ids' => $examIds,
                'difficulty' => 'medium',
                'language' => 'bilingual',
                'source_url' => $fact['book_url'],
                'source_reference' => 'wikidata-book-author',
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
            'book_url' => data_get($row, 'book.value'),
            'book_en' => data_get($row, 'bookLabelEn.value'),
            'book_hi' => data_get($row, 'bookLabelHi.value'),
            'author_url' => data_get($row, 'author.value'),
            'author_en' => data_get($row, 'authorLabelEn.value'),
            'author_hi' => data_get($row, 'authorLabelHi.value'),
        ];

        // SPARQL entity identifiers use HTTP; provenance links use the secure official URL.
        $fact['book_url'] = preg_replace('#^http://www\.wikidata\.org/entity/(Q[1-9][0-9]*)$#', 'https://www.wikidata.org/entity/$1', (string) $fact['book_url']);

        return collect($fact)->every(fn ($value) => is_string($value) && trim($value) !== '') ? $fact : null;
    }

    private function query(int $limit, int $offset): string
    {
        return <<<SPARQL
SELECT DISTINCT ?book ?bookLabelEn ?bookLabelHi ?author ?authorLabelEn ?authorLabelHi WHERE {
  ?book wdt:P50 ?author;
        wdt:P31/wdt:P279* wd:Q571;
        rdfs:label ?bookLabelEn;
        rdfs:label ?bookLabelHi.
  ?author wdt:P31 wd:Q5;
          rdfs:label ?authorLabelEn;
          rdfs:label ?authorLabelHi.
  FILTER NOT EXISTS { ?book wdt:P50 ?otherAnswer. FILTER(?otherAnswer != ?author) }
  FILTER(LANG(?bookLabelEn) = "en")
  FILTER(LANG(?bookLabelHi) = "hi")
  FILTER(LANG(?authorLabelEn) = "en")
  FILTER(LANG(?authorLabelHi) = "hi")
}
ORDER BY ?book ?author ?bookLabelEn ?authorLabelEn
LIMIT {$limit}
OFFSET {$offset}
SPARQL;
    }
}
