<?php

namespace App\Services;

use App\Models\ContentSource;
use App\Models\Question;
use App\Models\Subject;
use App\Models\Topic;
use Closure;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class CurrentAffairsTopicMappingService
{
    public function topicForSource(Subject $subject, ?ContentSource $source): ?Topic
    {
        if ($source?->slug !== 'reserve-bank-of-india') {
            return null;
        }

        if ($subject->name !== 'Current Affairs') {
            throw new RuntimeException('RBI current affairs require the Current Affairs subject.');
        }

        return $subject->topics()
            ->where('slug', 'economy-and-banking')
            ->where('is_active', true)
            ->firstOrFail();
    }

    /**
     * Run backup and live checks inside the same transaction as the mapping.
     * Only reviewed, published RBI questions without an existing topic qualify.
     */
    public function mapExistingRbiQuestions(Closure $backup, Closure $verify): int
    {
        return DB::transaction(function () use ($backup, $verify): int {
            $subject = Subject::where('name', 'Current Affairs')->firstOrFail();
            $source = ContentSource::where('slug', 'reserve-bank-of-india')->firstOrFail();
            $topic = $this->topicForSource($subject, $source);
            $questions = Question::query()
                ->where('subject_id', $subject->id)
                ->where('content_source_id', $source->id)
                ->where('source_reference', 'reserve-bank-of-india')
                ->where('generation_method', 'automated')
                ->where('verification_status', 'verified')
                ->where('is_current_affairs', true)
                ->where('is_active', true)
                ->where('is_published', true)
                ->whereNull('topic_id')
                ->orderBy('id')
                ->lockForUpdate()
                ->get(['id', 'topic_id']);

            $backup($questions->pluck('topic_id', 'id')->all(), $topic->id);

            // Change only the topic metadata; preserve question content and dates.
            $count = $questions->isEmpty() ? 0 : DB::table('questions')
                ->whereIn('id', $questions->modelKeys())
                ->whereNull('topic_id')
                ->update(['topic_id' => $topic->id]);

            if ($count !== $questions->count()) {
                throw new RuntimeException('The reviewed questions changed during topic mapping.');
            }

            $verify($topic, $count);

            return $count;
        });
    }
}
