<?php

namespace App\Services;

use App\Models\ContentSource;
use App\Models\Question;
use App\Models\Subject;
use App\Models\Topic;
use Illuminate\Support\Collection;
use RuntimeException;

class WikidataOrganizationChronologyHardImporter
{
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

        $facts = $this->facts($source->id);
        if ($facts->count() < 8) {
            throw new RuntimeException('At least eight verified bilingual organization inception facts are required.');
        }

        $subject = Subject::where('name', 'General Knowledge')->firstOrFail();
        $topic = Topic::where('subject_id', $subject->id)->where('name', 'Organizations')->firstOrFail();
        $examIds = $subject->exams()->where('is_active', true)->pluck('exams.id')->all();
        $questions = $this->groups($facts, $limit)->map(
            fn (Collection $group) => $this->payload($group, $subject->id, $topic->id, $examIds)
        )->all();

        if ($dryRun) {
            return ['fetched' => count($questions), 'accepted' => count($questions), 'duplicates' => 0,
                'rejected' => 0, 'dry_run' => true];
        }

        $batch = $this->ingestion->ingest($questions, $source, 'json');

        return ['fetched' => count($questions), 'accepted' => $batch->accepted_count,
            'duplicates' => $batch->duplicate_count, 'rejected' => $batch->rejected_count, 'dry_run' => false];
    }

    private function facts(int $sourceId): Collection
    {
        return Question::query()
            ->where('content_source_id', $sourceId)
            ->where('source_reference', 'wikidata-international-organization-inception-year')
            ->where('verification_status', 'verified')->where('is_published', true)->where('is_active', true)
            ->with(['options' => fn ($query) => $query->where('is_correct', true)])
            ->get()->map(function (Question $question) {
                $answer = $question->options->first();
                if (! $answer || ! preg_match('/^In which year was (.+) established\?$/u', $question->question_text, $en)
                    || ! preg_match('/^(.+) की स्थापना किस वर्ष हुई थी\?$/u', (string) $question->question_text_hi, $hi)
                    || ! preg_match('/^\d{4}$/D', $answer->option_text)
                    || ! preg_match('~/entity/(Q\d+)$~', (string) $question->source_url, $entity)) {
                    return null;
                }

                return ['entity_id' => $entity[1], 'name_en' => trim($en[1]), 'name_hi' => trim($hi[1]),
                    'year' => (int) $answer->option_text];
            })->filter()->groupBy('entity_id')
            ->filter(fn (Collection $rows) => $rows->unique('year')->count() === 1)
            ->map(fn (Collection $rows) => $rows->first())->values();
    }

    private function groups(Collection $facts, int $limit): Collection
    {
        $count = $facts->count();

        return collect(range(0, $limit - 1))->map(function (int $index) use ($facts, $count) {
            return collect([$index, $index + 7, $index + 17, $index + 29])
                ->map(fn (int $position) => $facts[$position % $count])->unique('entity_id')->values();
        })->filter(fn (Collection $group) => $group->count() === 4 && $group->unique('year')->count() === 4)
            ->unique(fn (Collection $group) => $group->pluck('entity_id')->sort()->implode('|'))->values();
    }

    private function payload(Collection $group, int $subjectId, int $topicId, array $examIds): array
    {
        $ascending = $group->sortBy('year')->values();
        $orders = collect([
            $ascending,
            $ascending->reverse()->values(),
            collect([$ascending[1], $ascending[0], $ascending[3], $ascending[2]]),
            collect([$ascending[2], $ascending[0], $ascending[3], $ascending[1]]),
        ])->unique(fn (Collection $order) => $order->pluck('entity_id')->implode('|'))->values();
        $namesEn = $group->pluck('name_en')->implode(', ');
        $namesHi = $group->pluck('name_hi')->implode(', ');
        $explanationEn = $ascending->map(fn (array $fact) => $fact['name_en'].' ('.$fact['year'].')')->implode(' < ');
        $explanationHi = $ascending->map(fn (array $fact) => $fact['name_hi'].' ('.$this->hindiNumber($fact['year']).')')->implode(' < ');
        $entities = $group->pluck('entity_id')->sort();

        return [
            'question_text' => "Which option arranges {$namesEn} in increasing order of establishment year?",
            'question_text_hi' => "कौन-सा विकल्प {$namesHi} को स्थापना वर्ष के बढ़ते क्रम में व्यवस्थित करता है?",
            'explanation' => "The correct chronological order is {$explanationEn}.",
            'explanation_hi' => "सही कालानुक्रमिक क्रम {$explanationHi} है।",
            'subject_id' => $subjectId, 'topic_id' => $topicId, 'exam_ids' => $examIds,
            'difficulty' => 'hard', 'language' => 'bilingual',
            'source_url' => 'https://www.wikidata.org/w/api.php?action=wbgetentities&format=json&ids='.$entities->implode('%7C'),
            'source_reference' => 'wikidata-organization-chronology:'.$entities->implode(','),
            'source_published_at' => now()->toDateString(), 'generation_method' => 'automated',
            'options' => $orders->map(fn (Collection $order, int $index) => [
                'option_text' => $order->pluck('name_en')->implode(' → '),
                'option_text_hi' => $order->pluck('name_hi')->implode(' → '),
                'is_correct' => $index === 0,
            ])->all(),
        ];
    }

    private function hindiNumber(int $number): string
    {
        return strtr((string) $number, ['0'=>'०','1'=>'१','2'=>'२','3'=>'३','4'=>'४','5'=>'५','6'=>'६','7'=>'७','8'=>'८','9'=>'९']);
    }
}
