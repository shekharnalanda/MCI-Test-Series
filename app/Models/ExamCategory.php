<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ExamCategory extends Model
{
    protected $guarded = [];

    public function exams(): HasMany
    {
        return $this->hasMany(Exam::class);
    }
}
