<?php

namespace App\Console\Commands;

use App\Models\Exam;
use App\Models\ExamCategory;
use App\Models\Test;
use App\Services\ChapterTestGenerator;
use Illuminate\Console\Command;
use RuntimeException;

class GenerateChapterTests extends Command
{
    protected $signature = 'test-series:chapters {--category=* : Reviewed category slugs} {--exam=* : Reviewed exam IDs} {--dry-run : Report without creating tests}';

    protected $description = 'Create one complete chapter test per reviewed exam/topic, using 25 or 10 verified questions';

    public function handle(ChapterTestGenerator $generator): int
    {
        $categories = $this->option('category');
        $examIds = $this->option('exam');
        if ($categories === [] && $examIds === []) {
            $this->error('Specify a reviewed category or exam.');

            return self::FAILURE;
        }
        if (ExamCategory::whereIn('slug', $categories)->count() !== count(array_unique($categories))
            || Exam::whereIn('id', $examIds)->count() !== count(array_unique($examIds))) {
            $this->error('An exam or category does not exist.');

            return self::FAILURE;
        }
        $exams = Exam::where('is_active', true)
            ->when($categories !== [], fn ($q) => $q->whereHas('category', fn ($c) => $c->whereIn('slug', $categories)))
            ->when($examIds !== [], fn ($q) => $q->whereIn('id', $examIds))->orderBy('id')->get();
        $generated = $existing = $insufficient = 0;
        foreach ($exams as $exam) {
            foreach ($generator->topics($exam) as $topic) {
                if (Test::where('exam_id', $exam->id)->where('is_active', true)->chapter((int) $topic->subject_id, (int) $topic->id)->exists()) {
                    $existing++;

                    continue;
                }
                $pool = $generator->eligibleQuery($exam, $topic)->count();
                if ($pool < 10) {
                    $insufficient++;

                    continue;
                }
                if (! $this->option('dry-run')) {
                    try {
                        $generator->generate($exam, $topic, $pool >= 25 ? 25 : 10);
                    } catch (RuntimeException $error) {
                        $this->error($exam->name.': '.$error->getMessage());

                        return self::FAILURE;
                    }
                }
                $generated++;
            }
        }
        $this->info(($this->option('dry-run') ? 'Planned' : 'Created')." {$generated} chapter tests; {$existing} already available; {$insufficient} insufficient chapter pools.");

        return self::SUCCESS;
    }
}
