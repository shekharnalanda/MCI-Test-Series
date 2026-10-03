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
        {--min-pool-multiple=3 : Require this many eligible questions per requested question}
        {--max-per-exam=10 : Maximum automatic tests of this type per exam and cycle}
        {--cycle=lifetime : lifetime or monthly}
        {--max-total=0 : Maximum new tests across all exams in one run; 0 is unlimited}';

    protected $description = 'Generate fair-rotation tests for every active exam with enough questions';

    public function handle(AutomaticTestGenerator $generator): int
    {
        $perExam = max(1, (int) $this->option('per-exam'));
        $questions = max(1, (int) $this->option('questions'));
        $maxPerExam = max(1, (int) $this->option('max-per-exam'));
        $minPoolMultiple = max(1, (int) $this->option('min-pool-multiple'));
        $type = (string) $this->option('type');
        $cycle = (string) $this->option('cycle');
        $maxTotal = max(0, (int) $this->option('max-total'));
        if (! in_array($cycle, ['lifetime', 'monthly'], true) || ! in_array($type, ['practice', 'full_mock', 'topic', 'previous_year', 'special'], true)
            || ! in_array($this->option('difficulty'), ['easy', 'medium', 'hard', 'mixed'], true)) {
            $this->error('Invalid generation cycle, type or difficulty.');

            return self::FAILURE;
        }
        $generated = 0;
        $skipped = 0;

        Exam::query()
            ->where('is_active', true)
            ->orderBy(Test::selectRaw('MAX(created_at)')->whereColumn('tests.exam_id', 'exams.id')
                ->where('test_type', $type)->where('auto_generated', true))
            ->orderBy('exams.id')
            ->each(function (Exam $exam) use (
                $generator,
                $perExam,
                $questions,
                $maxPerExam,
                $type,
                $minPoolMultiple,
                $cycle,
                $maxTotal,
                &$generated,
                &$skipped
            ) {
                if ($maxTotal > 0 && $generated >= $maxTotal) {
                    return false;
                }
                $existing = Test::query()
                    ->where('exam_id', $exam->id)
                    ->where('test_type', $type)
                    ->where('auto_generated', true)
                    ->when($cycle === 'lifetime', fn ($q) => $q->where('is_active', true))
                    ->when($cycle === 'monthly', fn ($q) => $q->where('created_at', '>=', now('Asia/Kolkata')->startOfMonth()->utc()))
                    ->count();

                $toGenerate = min($perExam, max(0, $maxPerExam - $existing));

                if ($toGenerate === 0) {
                    $skipped++;

                    return;
                }

                for ($index = 0; $index < $toGenerate && ($maxTotal === 0 || $generated < $maxTotal); $index++) {
                    try {
                        $generator->generate(
                            $exam,
                            $questions,
                            (string) $this->option('difficulty'),
                            $type,
                            $minPoolMultiple,
                            $cycle === 'monthly' ? $maxPerExam : null
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
