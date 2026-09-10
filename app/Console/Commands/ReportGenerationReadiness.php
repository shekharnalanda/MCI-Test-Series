<?php

namespace App\Console\Commands;

use App\Models\Exam;
use App\Services\AutomaticTestGenerator;
use Illuminate\Console\Command;

class ReportGenerationReadiness extends Command
{
    protected $signature = 'test-series:readiness
        {--questions=100 : Questions required in each test}
        {--min-pool-multiple=3 : Required diversity-pool multiple}
        {--only-not-ready : Show only exams that do not pass the quality gate}';

    protected $description = 'Audit exam-wise generation readiness without creating or changing tests';

    public function handle(AutomaticTestGenerator $generator): int
    {
        $questions = (int) $this->option('questions');
        $multiple = (int) $this->option('min-pool-multiple');

        if ($questions < 1 || $questions > 500 || $multiple < 1) {
            $this->error('Questions must be 1-500 and min-pool-multiple must be at least 1.');

            return self::INVALID;
        }

        $required = $questions * $multiple;
        $rows = [];
        $ready = 0;
        $notReady = 0;

        Exam::query()
            ->where('is_active', true)
            ->orderBy('id')
            ->each(function (Exam $exam) use (
                $generator,
                $required,
                &$rows,
                &$ready,
                &$notReady
            ): void {
                $query = $generator->eligibleQuery($exam);
                $counts = (clone $query)
                    ->selectRaw('difficulty, COUNT(*) AS aggregate')
                    ->groupBy('difficulty')
                    ->pluck('aggregate', 'difficulty');
                $eligible = (int) $counts->sum();
                $topics = (clone $query)
                    ->whereNotNull('topic_id')
                    ->distinct()
                    ->count('topic_id');
                $isReady = $eligible >= $required;

                $isReady ? $ready++ : $notReady++;

                if ($this->option('only-not-ready') && $isReady) {
                    return;
                }

                $rows[] = [
                    $exam->id,
                    $exam->name,
                    $eligible,
                    (int) ($counts['easy'] ?? 0),
                    (int) ($counts['medium'] ?? 0),
                    (int) ($counts['hard'] ?? 0),
                    $topics,
                    $isReady ? 'READY' : 'NEEDS CONTENT',
                ];
            });

        $this->table(
            ['ID', 'Exam', 'Eligible', 'Easy', 'Medium', 'Hard', 'Topics', 'Status'],
            $rows
        );
        $this->info(
            "Read-only audit: {$ready} ready; {$notReady} need content; " .
            "quality gate requires {$required} eligible questions per exam."
        );

        return self::SUCCESS;
    }
}
