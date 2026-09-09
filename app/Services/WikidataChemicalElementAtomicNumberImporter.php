<?php

namespace App\Services;

use App\Models\ContentSource;
use App\Models\Subject;
use App\Models\Topic;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class WikidataChemicalElementAtomicNumberImporter
{
    private const ENDPOINT = 'https://query.wikidata.org/sparql';

    public function __construct(
        private readonly QuestionIngestionService $ingestion,
        private readonly TrustedSourcePolicy $sourcePolicy,
        private readonly TrustedSourceHealthService $health,
    ) {}

    public function import(int $limit = 250, bool $dryRun = false): array
    {
        $limit = max(10, min($limit, 250));
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
            ->groupBy('element_url')->filter(fn (Collection $rows) => $rows->unique('atomic_number')->count() === 1)
            ->map(fn (Collection $rows) => $rows->first())->unique('atomic_number')->values();
        if ($facts->count() < 4) {
            throw new RuntimeException('At least four unambiguous bilingual chemical-element facts are required.');
        }

        $subject = Subject::where('name', 'Chemistry')->firstOrFail();
        $topic = Topic::where('subject_id', $subject->id)->where('name', 'Periodic Table')->firstOrFail();
        $examIds = $subject->exams()->where('is_active', true)->pluck('exams.id')->all();
        $numbers = $facts->pluck('atomic_number')->unique()->values();
        $questions = $facts->map(function (array $fact) use ($numbers, $subject, $topic, $examIds): array {
            $options = $numbers->reject(fn (int $number) => $number === $fact['atomic_number'])
                ->sortBy(fn (int $number) => hash('sha256', $fact['element_url'].'|'.$number))->take(3)
                ->push($fact['atomic_number'])
                ->sortBy(fn (int $number) => hash('sha256', $fact['element_url'].'|option|'.$number))->values()
                ->map(fn (int $number) => [
                    'option_text' => (string) $number,
                    'option_text_hi' => $this->devanagariNumber($number),
                    'is_correct' => $number === $fact['atomic_number'],
                ])->all();

            return [
                'question_text' => "What is the atomic number of {$fact['element_en']}?",
                'question_text_hi' => "{$fact['element_hi']} की परमाणु संख्या क्या है?",
                'explanation' => "The atomic number of {$fact['element_en']} is {$fact['atomic_number']}.",
                'explanation_hi' => "{$fact['element_hi']} की परमाणु संख्या {$this->devanagariNumber($fact['atomic_number'])} है।",
                'subject_id' => $subject->id, 'topic_id' => $topic->id, 'exam_ids' => $examIds,
                'difficulty' => 'medium', 'language' => 'bilingual', 'source_url' => $fact['element_url'],
                'source_reference' => 'wikidata-chemical-element-atomic-number', 'source_published_at' => now()->toDateString(),
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
        $url = data_get($row, 'element.value');
        $nameEn = data_get($row, 'elementLabelEn.value');
        $nameHi = data_get($row, 'elementLabelHi.value');
        $rawNumber = data_get($row, 'atomicNumber.value');
        if (! is_string($url) || trim($url) === '' || ! is_string($nameEn) || trim($nameEn) === ''
            || ! is_string($nameHi) || trim($nameHi) === '' || ! is_string($rawNumber)
            || preg_match('/^\\d+$/D', $rawNumber) !== 1) {
            return null;
        }
        $number = (int) $rawNumber;
        if ($number < 1 || $number > 118) {
            return null;
        }

        return ['element_url' => $url, 'element_en' => $nameEn, 'element_hi' => $nameHi, 'atomic_number' => $number];
    }

    private function devanagariNumber(int $number): string
    {
        return strtr((string) $number, ['0' => '०', '1' => '१', '2' => '२', '3' => '३', '4' => '४',
            '5' => '५', '6' => '६', '7' => '७', '8' => '८', '9' => '९']);
    }

    private function query(int $limit): string
    {
        return <<<SPARQL
SELECT DISTINCT ?element ?elementLabelEn ?elementLabelHi ?atomicNumber WHERE {
  ?element wdt:P31 wd:Q11344;
           wdt:P1086 ?atomicNumber;
           rdfs:label ?elementLabelEn;
           rdfs:label ?elementLabelHi.
  FILTER(LANG(?elementLabelEn) = "en")
  FILTER(LANG(?elementLabelHi) = "hi")
  FILTER(?atomicNumber >= 1 && ?atomicNumber <= 118)
}
ORDER BY ?atomicNumber
LIMIT {$limit}
SPARQL;
    }
}
