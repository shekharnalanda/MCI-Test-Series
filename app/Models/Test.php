<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Test extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'positive_marks' => 'decimal:2',
            'negative_marks' => 'decimal:2',
            'randomize_questions' => 'boolean',
            'randomize_options' => 'boolean',
            'auto_generated' => 'boolean',
            'is_demo' => 'boolean',
            'is_active' => 'boolean',
            'available_from' => 'datetime',
            'available_until' => 'datetime',
            'answers_available_at' => 'datetime',
            'generation_rules' => 'array',
        ];
    }

    public function scopeChapter(Builder $query, int $subjectId, int $topicId): Builder
    {
        return $query->where('tests.test_type', 'topic')
            ->where('tests.subject_id', $subjectId)->where('tests.topic_id', $topicId)
            ->whereHas('topic', fn (Builder $topic) => $topic->where('subject_id', $subjectId)->where('is_active', true)
                ->whereHas('subject', fn (Builder $subject) => $subject->where('is_active', true)))
            ->where('tests.total_questions', '>', 0)
            ->whereRaw('tests.total_questions = (SELECT COUNT(*) FROM question_test WHERE question_test.test_id = tests.id)')
            ->whereDoesntHave('questions', function (Builder $questions) use ($subjectId, $topicId): void {
                $questions->whereNull('questions.subject_id')->orWhereNull('questions.topic_id')
                    ->orWhere('questions.subject_id', '!=', $subjectId)->orWhere('questions.topic_id', '!=', $topicId)
                    ->orWhere('questions.is_active', false)->orWhere('questions.is_published', false)
                    ->orWhere('questions.verification_status', '!=', 'verified');
            });
    }

    public function series(): BelongsTo
    {
        return $this->belongsTo(TestSeries::class, 'test_series_id');
    }

    public function exam(): BelongsTo
    {
        return $this->belongsTo(Exam::class);
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function topic(): BelongsTo
    {
        return $this->belongsTo(Topic::class);
    }

    public function questions(): BelongsToMany
    {
        return $this->belongsToMany(Question::class)
            ->withPivot(['sort_order', 'marks', 'negative_marks']);
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(TestAttempt::class);
    }
}
