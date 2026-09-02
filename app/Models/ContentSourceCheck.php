<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContentSourceCheck extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'healthy' => 'boolean',
            'checked_at' => 'datetime',
        ];
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(ContentSource::class, 'content_source_id');
    }
}
