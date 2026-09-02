<?php

namespace App\Console\Commands;

use App\Models\Exam;
use App\Services\AutomaticTestGenerator;
use Illuminate\Console\Command;
use RuntimeException;

class GenerateAutomaticTestSeries extends Command
{
    protected $signature = 'test-series:generate
        {--per-exam=1 : Tests to generate for each active exam}
        {--questions=25 : Questions in each generated test}
        {--difficulty=mixed : easy, medium, hard, or mixed}
        {--type=practice : Generated test type}';

    protected $description = 'Generate fair-rotation tests for every active exam with enough questions';

    public function handle(AutomaticTestGenerator $generator): int
    {
        $perExam = max(1, (int) $this->option('per-exam'));
        $questions = max(1, (int) $this->option('questions'));
        $generated = 0;
        $skipped = 0;

        Exam::query()
            ->where('is_active', true)
            ->orderBy('id')
            ->each(function (Exam $exam) use (
                $generator,
                $perExam,
                $questions,
                &$generated,
                &$skipped
            ): void {
                for ($index = 0; $index < $perExam; $index++) {
                    try {
                        $generator->generate(
                            $exam,
                            $questions,
                            (string) $this->option('difficulty'),
                            (string) $this->option('type')
                        );
                        $generated++;
                    } catch (RuntimeException $exception) {
                        $skipped++;
                        $this->warn($exam->name.': '.$exception->getMessage());

                        break;
                    }
                }
            });

        $this->info("Generated {$generated} automatic test(s); skipped {$skipped} exam(s).");

        return self::SUCCESS;
    }
}
