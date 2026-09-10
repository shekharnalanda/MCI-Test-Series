<?php

namespace App\Console\Commands;

use App\Models\Question;
use App\Services\HardQuestionQualityGate;
use Illuminate\Console\Command;

class AuditHardQuestionQuality extends Command
{
    protected $signature = 'question-bank:audit-hard-quality
        {--strict : Return failure when an invalid published Hard question exists}';

    protected $description = 'Audit Hard questions for trusted bilingual provenance and syllabus alignment';

    public function handle(HardQuestionQualityGate $gate): int
    {
        $total = 0;
        $invalid = 0;

        Question::query()
            ->where('difficulty', 'hard')
            ->where('verification_status', 'verified')
            ->where('is_published', true)
            ->with(['source', 'options', 'exams'])
            ->chunkById(200, function ($questions) use (
                $gate,
                &$total,
                &$invalid
            ): void {
                foreach ($questions as $question) {
                    $total++;

                    if (!$gate->acceptsStored($question)) {
                        $invalid++;
                    }
                }
            });

        $this->table(
            ['Metric', 'Count'],
            [
                ['Published verified Hard questions', $total],
                ['Invalid Hard questions', $invalid],
            ]
        );

        if ($invalid > 0) {
            $this->error('Hard-question quality audit failed.');

            return $this->option('strict')
                ? self::FAILURE
                : self::SUCCESS;
        }

        $this->info('Hard-question quality audit passed.');

        return self::SUCCESS;
    }
}
