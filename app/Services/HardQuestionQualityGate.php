<?php

namespace App\Services;

use App\Models\ContentSource;
use App\Models\Question;
use Illuminate\Support\Facades\DB;

class HardQuestionQualityGate
{
    public function __construct(
        private readonly TrustedSourcePolicy $sourcePolicy
    ) {}

    public function accepts(array $item, ?ContentSource $source): bool
    {
        if (
            !$source ||
            !$this->sourcePolicy->canGenerateQuestions($source) ||
            ($item['language'] ?? null) !== 'bilingual' ||
            !$this->hasBilingualCore($item) ||
            !$this->hasFourBilingualOptions($item['options'] ?? []) ||
            !$this->hasMatchingProvenance($item, $source)
        ) {
            return false;
        }

        $subjectId = (int) ($item['subject_id'] ?? 0);
        $topicId = (int) ($item['topic_id'] ?? 0);
        $examIds = collect($item['exam_ids'] ?? [])
            ->map(fn ($id): int => (int) $id)
            ->filter()
            ->unique()
            ->values();

        if ($subjectId < 1 || $topicId < 1 || $examIds->isEmpty()) {
            return false;
        }

        if (!DB::table('topics')
            ->where('id', $topicId)
            ->where('subject_id', $subjectId)
            ->where('is_active', true)
            ->exists()) {
            return false;
        }

        return DB::table('exam_subject')
            ->where('subject_id', $subjectId)
            ->whereIn('exam_id', $examIds->all())
            ->distinct()
            ->count('exam_id') === $examIds->count();
    }

    public function acceptsStored(Question $question): bool
    {
        $source = $question->source;

        return $this->accepts([
            'question_text' => $question->question_text,
            'question_text_hi' => $question->question_text_hi,
            'explanation' => $question->explanation,
            'explanation_hi' => $question->explanation_hi,
            'language' => $question->language,
            'subject_id' => $question->subject_id,
            'topic_id' => $question->topic_id,
            'exam_ids' => $question->exams->modelKeys(),
            'source_url' => $question->source_url,
            'source_reference' => $question->source_reference,
            'source_published_at' => $question->source_published_at,
            'options' => $question->options->map(fn ($option): array => [
                'option_text' => $option->option_text,
                'option_text_hi' => $option->option_text_hi,
                'is_correct' => $option->is_correct,
            ])->all(),
        ], $source);
    }

    private function hasBilingualCore(array $item): bool
    {
        return filled($item['question_text'] ?? null)
            && filled($item['question_text_hi'] ?? null)
            && filled($item['explanation'] ?? null)
            && filled($item['explanation_hi'] ?? null);
    }

    private function hasFourBilingualOptions(array $options): bool
    {
        if (count($options) !== 4) {
            return false;
        }

        $english = [];
        $hindi = [];
        $correct = 0;

        foreach ($options as $option) {
            if (
                !filled($option['option_text'] ?? null) ||
                !filled($option['option_text_hi'] ?? null)
            ) {
                return false;
            }

            $english[] = mb_strtolower(trim((string) $option['option_text']));
            $hindi[] = mb_strtolower(trim((string) $option['option_text_hi']));
            $correct += (bool) ($option['is_correct'] ?? false) ? 1 : 0;
        }

        return count(array_unique($english)) === 4
            && count(array_unique($hindi)) === 4
            && $correct === 1;
    }

    private function hasMatchingProvenance(
        array $item,
        ContentSource $source
    ): bool {
        $url = trim((string) ($item['source_url'] ?? ''));
        $reference = trim((string) ($item['source_reference'] ?? ''));
        $publishedAt = $item['source_published_at'] ?? null;
        $timestamp = $publishedAt instanceof \DateTimeInterface
            ? $publishedAt->getTimestamp()
            : (is_scalar($publishedAt)
                ? strtotime((string) $publishedAt)
                : false);
        $sourceHost = strtolower((string) parse_url(
            (string) $source->base_url,
            PHP_URL_HOST
        ));
        $itemHost = strtolower((string) parse_url($url, PHP_URL_HOST));

        return filter_var($url, FILTER_VALIDATE_URL) !== false
            && parse_url($url, PHP_URL_SCHEME) === 'https'
            && $reference !== ''
            && $timestamp !== false
            && $timestamp <= now()->timestamp
            && $sourceHost !== ''
            && ($itemHost === $sourceHost
                || str_ends_with($itemHost, '.'.$sourceHost));
    }
}
