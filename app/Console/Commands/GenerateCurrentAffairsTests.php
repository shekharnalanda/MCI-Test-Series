<?php

namespace App\Console\Commands;

use App\Services\CurrentAffairsTestGenerator;
use Illuminate\Console\Command;
use RuntimeException;

class GenerateCurrentAffairsTests extends Command
{
    protected $signature =
        'mci:current-affairs-tests
        {period=weekly : daily, weekly or monthly}
        {--questions=10 : Number of questions}';

    protected $description =
        'Generate automatic current affairs test from verified question bank';

    public function handle(
        CurrentAffairsTestGenerator $generator
    ): int {
        $period = strtolower(
            (string) $this->argument('period')
        );

        $questionCount = (int)
            $this->option('questions');

        if (
            !in_array(
                $period,
                ['daily', 'weekly', 'monthly'],
                true
            )
        ) {
            $this->error(
                'Period must be daily, weekly or monthly.'
            );

            return self::FAILURE;
        }

        if (
            $questionCount < 1 ||
            $questionCount > 500
        ) {
            $this->error(
                'Question count must be between 1 and 500.'
            );

            return self::FAILURE;
        }

        try {
            $test = $generator->generate(
                $period,
                $questionCount
            );

            $this->info(
                'Current Affairs Test Generated'
            );

            $this->line(
                'Test: '.$test->title
            );

            $this->line(
                'Questions: '.
                $test->questions()->count()
            );

            return self::SUCCESS;

        } catch (RuntimeException $e) {

            /*
             * Insufficient fresh questions is not a system crash.
             * No incomplete test is created.
             */
            $this->warn($e->getMessage());

            return self::SUCCESS;
        }
    }
}
