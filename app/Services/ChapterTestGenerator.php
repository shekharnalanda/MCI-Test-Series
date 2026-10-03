<?php

namespace App\Services;

use App\Models\Exam;
use App\Models\Question;
use App\Models\Test;
use App\Models\TestSeries;
use App\Models\Topic;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ChapterTestGenerator
{
    public function __construct(private readonly AutomaticTestGenerator $generator) {}

    public function eligibleQuery(Exam $exam, ?Topic $topic = null): Builder
    {
        return $this->generator->eligibleQuery($exam)
            ->whereNotNull('questions.topic_id')
            ->whereHas('topic', fn (Builder $q) => $q->where('topics.is_active', true)
                ->whereColumn('topics.subject_id', 'questions.subject_id')
                ->whereHas('subject', fn (Builder $s) => $s->where('is_active', true)))
            ->has('options', '=', 4)
            ->whereHas('options', fn (Builder $q) => $q->where('is_correct', true), '=', 1)
            ->whereDoesntHave('options', fn (Builder $q) => $q->whereNull('option_text')->orWhere('option_text', ''))
            ->when($topic, fn (Builder $q) => $q->where('questions.topic_id', $topic->id)->where('questions.subject_id', $topic->subject_id));
    }

    public function topics(Exam $exam): Collection
    {
        return Topic::whereIn('id', $this->eligibleQuery($exam)->select('questions.topic_id'))
            ->with('subject')->orderBy('id')->get();
    }

    public function generate(Exam $exam, Topic $topic, int $questionCount = 25): Test
    {
        if (! $exam->is_active || ! $topic->is_active || $questionCount < 10 || $questionCount > 100) {
            throw new RuntimeException('An active exam/chapter and 10–100 questions are required.');
        }

        return DB::transaction(function () use ($exam, $topic, $questionCount): Test {
            // Serialize creation for this exam so repeated or overlapping jobs are safe.
            Exam::whereKey($exam->id)->lockForUpdate()->firstOrFail();
            $existing = Test::where('exam_id', $exam->id)->where('is_active', true)
                ->chapter((int) $topic->subject_id, (int) $topic->id)->first();
            if ($existing) {
                return $existing;
            }
            $query = $this->eligibleQuery($exam, $topic);
            $pool = (clone $query)->count();
            if ($pool < $questionCount) {
                throw new RuntimeException("Only {$pool} eligible chapter questions; {$questionCount} required.");
            }
            $questions = $query->orderBy('usage_count')->orderBy('questions.id')->limit($questionCount)->get();
            if ($questions->count() !== $questionCount) {
                throw new RuntimeException('The chapter question pool changed during generation.');
            }
            $series = TestSeries::firstOrCreate(['slug' => 'chapter-'.$exam->slug.'-'.$topic->id], [
                'exam_id' => $exam->id, 'name' => $exam->name.' — '.$topic->name,
                'series_type' => 'topic', 'price' => 0, 'is_free' => false, 'is_active' => true,
            ]);
            $test = Test::create([
                'test_series_id' => $series->id, 'exam_id' => $exam->id,
                'subject_id' => $topic->subject_id, 'topic_id' => $topic->id,
                'title' => $exam->name.' — '.$topic->name.' — Chapter Test',
                'title_hi' => ($exam->name_hi ?: $exam->name).' — '.($topic->name_hi ?: $topic->name).' — अध्याय टेस्ट',
                'instructions' => 'This test contains only verified questions from the selected chapter.',
                'test_type' => 'topic', 'total_questions' => $questionCount,
                'duration_minutes' => max(10, $questionCount), 'positive_marks' => 1, 'negative_marks' => 0.25,
                'randomize_questions' => true, 'randomize_options' => true,
                'auto_generated' => true, 'is_demo' => false, 'is_active' => true,
                'generation_rules' => ['chapter_only' => true, 'subject_id' => $topic->subject_id,
                    'topic_id' => $topic->id, 'question_count' => $questionCount, 'eligible_pool_size' => $pool,
                    'selection' => 'least_used', 'verified_only' => true, 'published_only' => true],
            ]);
            $sync = [];
            foreach ($questions as $index => $question) {
                $sync[$question->id] = ['sort_order' => $index + 1, 'marks' => 1, 'negative_marks' => 0.25];
            }
            $test->questions()->sync($sync);
            Question::whereKey($questions->modelKeys())->increment('usage_count');

            return $test->fresh(['questions', 'topic', 'subject']);
        });
    }
}
