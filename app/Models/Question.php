<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Question extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'is_current_affairs' => 'boolean',
            'current_affair_date' => 'date',
            'source_published_at' => 'date',
            'last_used_at' => 'datetime',
            'auto_publish' => 'boolean',
            'is_published' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function topic(): BelongsTo
    {
        return $this->belongsTo(Topic::class);
    }

    public function options(): HasMany
    {
        return $this->hasMany(QuestionOption::class);
    }

    public function exams(): BelongsToMany
    {
        return $this->belongsToMany(Exam::class)
            ->withPivot('relevance_score');
    }

    public function tests(): BelongsToMany
    {
        return $this->belongsToMany(Test::class)
            ->withPivot(['sort_order', 'marks', 'negative_marks']);
    }
}
