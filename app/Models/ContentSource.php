<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ContentSource extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'allow_current_affairs' => 'boolean',
            'allow_question_generation' => 'boolean',
            'auto_publish_allowed' => 'boolean',
            'is_active' => 'boolean',
            'last_checked_at' => 'datetime',
            'last_success_at' => 'datetime',
        ];
    }

    public function questions(): HasMany
    {
        return $this->hasMany(Question::class);
    }

    public function importBatches(): HasMany
    {
        return $this->hasMany(QuestionImportBatch::class);
    }

    public function currentAffairs(): HasMany
    {
        return $this->hasMany(CurrentAffairItem::class);
    }

    public function healthChecks(): HasMany
    {
        return $this->hasMany(ContentSourceCheck::class);
    }
}
