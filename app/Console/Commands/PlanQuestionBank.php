<?php

namespace App\Console\Commands;

use App\Services\QuestionBankPlanner;
use Illuminate\Console\Command;

class PlanQuestionBank extends Command
{
    protected $signature =
        'mci:question-bank-plan
        {--target=500 : Target questions per exam-subject pool}';

    protected $description =
        'Create or refresh large question bank generation targets';

    public function handle(
        QuestionBankPlanner $planner
    ): int {
        $target = (int)
            $this->option('target');

        if (
            $target < 1 ||
            $target > 100000
        ) {
            $this->error(
                'Target must be between 1 and 100000.'
            );

            return self::FAILURE;
        }

        $jobs = $planner->buildJobs(
            $target
        );

        $this->info(
            'Question Bank Planning Complete'
        );

        $this->line(
            "Active generation targets: {$jobs}"
        );

        return self::SUCCESS;
    }
}
