<?php

namespace App\Console\Commands;

use App\Models\Exam;
use App\Models\Test;
use App\Services\AutomaticTestGenerator;
use Illuminate\Console\Command;
use RuntimeException;

class GenerateAutomaticTestSeries extends Command
{
    protected $signature = 'test-series:generate
        {--per-exam=1 : Tests to generate for each active exam}
        {--questions=25 : Questions in each generated test}
        {--difficulty=mixed : easy, medium, hard, or mixed}
        {--type=practice : Generated test type}
        {--max-per-exam=10 : Maximum active automatic tests of this type per exam}';

    protected $description = 'Generate fair-rotation tests for every active exam with enough questions';

    public function handle(AutomaticTestGenerator $generator): int
    {
        $perExam = max(1, (int) $this->option('per-exam'));
        $questions = max(1, (int) $this->option('questions'));
        $maxPerExam = max(1, (int) $this->option('max-per-exam'));
        $type = (string) $this->option('type');
        $generated = 0;
        $skipped = 0;

        Exam::query()
            ->where('is_active', true)
            ->orderBy('id')
            ->each(function (Exam $exam) use (
                $generator,
                $perExam,
                $questions,
                $maxPerExam,
                $type,
                &$generated,
                &$skipped
            ): void {
                $existing = Test::query()
                    ->where('exam_id', $exam->id)
                    ->where('test_type', $type)
                    ->where('auto_generated', true)
                    ->where('is_active', true)
                    ->count();

                $toGenerate = min($perExam, max(0, $maxPerExam - $existing));

                if ($toGenerate === 0) {
                    $skipped++;

                    return;
                }

                for ($index = 0; $index < $toGenerate; $index++) {
                    try {
                        $generator->generate(
                            $exam,
                            $questions,
                            (string) $this->option('difficulty'),
                            $type
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
