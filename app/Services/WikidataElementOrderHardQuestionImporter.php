<?php

namespace App\Services;

use App\Models\ContentSource;
use App\Models\Subject;
use App\Models\Topic;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class WikidataElementOrderHardQuestionImporter
{
    private const ENDPOINT = 'https://query.wikidata.org/sparql';

    public function __construct(
        private readonly QuestionIngestionService $ingestion,
        private readonly TrustedSourcePolicy $sourcePolicy,
        private readonly TrustedSourceHealthService $health,
    ) {}

    public function import(int $limit = 10, bool $dryRun = false, string $format = 'order'): array
    {
        $limit = max(1, min($limit, 25));
        if (! in_array($format, ['order', 'range'], true)) {
            throw new RuntimeException('Format must be order or range.');
        }
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
            ->unique('atomic_number')->sortBy('atomic_number')->values();

        if ($facts->count() < 8) {
            throw new RuntimeException('At least eight unambiguous bilingual element facts are required.');
        }

        $subject = Subject::where('name', 'Chemistry')->firstOrFail();
        $topic = Topic::where('subject_id', $subject->id)->where('name', 'Periodic Table')->firstOrFail();
        $examIds = $subject->exams()->where('is_active', true)->pluck('exams.id')->all();
        $groups = $this->groups($facts, $limit);

        $questions = $groups->map(fn (Collection $group) => $this->payload(
            $group, $subject->id, $topic->id, $examIds, $format
        ))->all();

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
            $positions = [
                $index % $count,
                ($index + 7) % $count,
                ($index + 19) % $count,
                ($index + 31) % $count,
            ];

            return collect($positions)->unique()->map(fn (int $position) => $facts[$position])->values();
        })->filter(fn (Collection $group) => $group->count() === 4)->unique(
            fn (Collection $group) => $group->pluck('entity_id')->sort()->implode('|')
        )->values();
    }

    private function payload(
        Collection $group,
        int $subjectId,
        int $topicId,
        array $examIds,
        string $format
    ): array
    {
        if ($format === 'range') {
            return $this->rangePayload($group, $subjectId, $topicId, $examIds);
        }

        $ascending = $group->sortBy('atomic_number')->values();
        $orders = collect([
            $ascending,
            $ascending->reverse()->values(),
            collect([$ascending[1], $ascending[0], $ascending[3], $ascending[2]]),
            collect([$ascending[2], $ascending[0], $ascending[3], $ascending[1]]),
        ])->unique(fn (Collection $order) => $order->pluck('entity_id')->implode('|'))->values();
        $namesEn = $group->pluck('name_en')->implode(', ');
        $namesHi = $group->pluck('name_hi')->implode(', ');
        $explanationEn = $ascending->map(fn ($fact) => "{$fact['name_en']} ({$fact['atomic_number']})")->implode(' < ');
        $explanationHi = $ascending->map(fn ($fact) => "{$fact['name_hi']} ({$fact['atomic_number']})")->implode(' < ');

        return [
            'question_text' => "Which option arranges {$namesEn} in increasing order of atomic number?",
            'question_text_hi' => "कौन-सा विकल्प {$namesHi} को परमाणु संख्या के बढ़ते क्रम में व्यवस्थित करता है?",
            'explanation' => "Their increasing atomic-number order is {$explanationEn}.",
            'explanation_hi' => "इनकी परमाणु संख्या का बढ़ता क्रम {$explanationHi} है।",
            'subject_id' => $subjectId, 'topic_id' => $topicId, 'exam_ids' => $examIds,
            'difficulty' => 'hard', 'language' => 'bilingual',
            'source_url' => 'https://www.wikidata.org/w/api.php?action=wbgetentities&format=json&ids='.
                $group->pluck('entity_id')->sort()->implode('%7C'),
            'source_reference' => 'wikidata-element-order:'.$group->pluck('entity_id')->sort()->implode(','),
            'source_published_at' => now()->toDateString(), 'generation_method' => 'automated',
            'options' => $orders->map(fn (Collection $order, int $index) => [
                'option_text' => $order->pluck('name_en')->implode(' → '),
                'option_text_hi' => $order->pluck('name_hi')->implode(' → '),
                'is_correct' => $index === 0,
            ])->all(),
        ];
    }

    private function rangePayload(Collection $group, int $subjectId, int $topicId, array $examIds): array
    {
        $ascending = $group->sortBy('atomic_number')->values();
        $lowest = $ascending->first();
        $highest = $ascending->last();
        $range = $highest['atomic_number'] - $lowest['atomic_number'];
        $namesEn = $group->pluck('name_en')->implode(', ');
        $namesHi = $group->pluck('name_hi')->implode(', ');
        $values = collect([$range, $range + 1, max(1, $range - 1), $range + 2])->unique()->values();

        while ($values->count() < 4) {
            $values->push($range + $values->count() + 2);
        }

        $values = $values->sortBy(fn (int $value) => hash('sha256', $namesEn.'|'.$value))->values();

        return [
            'question_text' => "Among {$namesEn}, what is the difference between the highest and lowest atomic numbers?",
            'question_text_hi' => "{$namesHi} में सबसे बड़ी और सबसे छोटी परमाणु संख्याओं का अंतर कितना है?",
            'explanation' => "{$highest['name_en']} has atomic number {$highest['atomic_number']} and {$lowest['name_en']} has {$lowest['atomic_number']}; therefore the difference is {$range}.",
            'explanation_hi' => "{$highest['name_hi']} की परमाणु संख्या {$this->devanagariNumber($highest['atomic_number'])} और {$lowest['name_hi']} की {$this->devanagariNumber($lowest['atomic_number'])} है; अतः अंतर {$this->devanagariNumber($range)} है।",
            'subject_id' => $subjectId, 'topic_id' => $topicId, 'exam_ids' => $examIds,
            'difficulty' => 'hard', 'language' => 'bilingual',
            'source_url' => 'https://www.wikidata.org/w/api.php?action=wbgetentities&format=json&ids='.
                $group->pluck('entity_id')->sort()->implode('%7C'),
            'source_reference' => 'wikidata-element-range:'.$group->pluck('entity_id')->sort()->implode(','),
            'source_published_at' => now()->toDateString(), 'generation_method' => 'automated',
            'options' => $values->map(fn (int $value) => [
                'option_text' => (string) $value,
                'option_text_hi' => $this->devanagariNumber($value),
                'is_correct' => $value === $range,
            ])->all(),
        ];
    }

    private function devanagariNumber(int $number): string
    {
        return strtr((string) $number, [
            '0' => '०', '1' => '१', '2' => '२', '3' => '३', '4' => '४',
            '5' => '५', '6' => '६', '7' => '७', '8' => '८', '9' => '९',
        ]);
    }

    private function fact(array $row): ?array
    {
        $url = data_get($row, 'element.value');
        $nameEn = data_get($row, 'elementLabelEn.value');
        $nameHi = data_get($row, 'elementLabelHi.value');
        $rawNumber = data_get($row, 'atomicNumber.value');

        if (! is_string($url) || ! preg_match('~/entity/(Q\\d+)$~', $url, $entity)
            || ! is_string($nameEn) || trim($nameEn) === '' || ! is_string($nameHi) || trim($nameHi) === ''
            || ! is_string($rawNumber) || preg_match('/^\\d+$/D', $rawNumber) !== 1) {
            return null;
        }

        $number = (int) $rawNumber;

        return $number >= 1 && $number <= 118 ? [
            'entity_id' => $entity[1], 'name_en' => trim($nameEn), 'name_hi' => trim($nameHi),
            'atomic_number' => $number,
        ] : null;
    }

    private function query(): string
    {
        return <<<'SPARQL'
SELECT DISTINCT ?element ?elementLabelEn ?elementLabelHi ?atomicNumber WHERE {
  ?element wdt:P31 wd:Q11344; wdt:P1086 ?atomicNumber;
           rdfs:label ?elementLabelEn; rdfs:label ?elementLabelHi.
  FILTER(LANG(?elementLabelEn) = "en")
  FILTER(LANG(?elementLabelHi) = "hi")
  FILTER(?atomicNumber >= 1 && ?atomicNumber <= 118)
}
ORDER BY ?atomicNumber
SPARQL;
    }
}
