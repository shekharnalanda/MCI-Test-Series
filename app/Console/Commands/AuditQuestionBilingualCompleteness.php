<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class AuditQuestionBilingualCompleteness extends Command
{
    protected $signature = 'question-bank:audit-bilingual
        {--strict : Return failure when published bilingual content is incomplete}';

    protected $description = 'Audit published questions and options for Hindi and English completeness';

    public function handle(): int
    {
        $published = DB::table('questions')->where('is_published', true);
        $total = (clone $published)->count();

        if ($total === 0) {
            $this->info('No published questions found for bilingual audit.');

            return self::SUCCESS;
        }

        $missingQuestionHindi = (clone $published)
            ->where(function ($query): void {
                $query->whereNull('question_text_hi')
                    ->orWhereRaw("TRIM(question_text_hi) = ''");
            })
            ->count();

        $missingOptionHindi = DB::table('questions')
            ->join('question_options', 'question_options.question_id', '=', 'questions.id')
            ->where('questions.is_published', true)
            ->where(function ($query): void {
                $query->whereNull('question_options.option_text_hi')
                    ->orWhereRaw("TRIM(question_options.option_text_hi) = ''");
            })
            ->distinct()
            ->count('questions.id');

        $this->table(
            ['Published', 'Missing Hindi question', 'Questions with missing Hindi option'],
            [[$total, $missingQuestionHindi, $missingOptionHindi]]
        );

        $incomplete = $missingQuestionHindi > 0 || $missingOptionHindi > 0;

        if ($incomplete) {
            $this->warn('Published question bank has incomplete bilingual content.');

            return $this->option('strict') ? self::FAILURE : self::SUCCESS;
        }

        $this->info('Published question bank is bilingual-complete.');

        return self::SUCCESS;
    }
}
