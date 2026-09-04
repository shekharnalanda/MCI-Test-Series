<?php

namespace App\Console\Commands;

use App\Services\VerifiedCurrentAffairsQuestionGenerator;
use Illuminate\Console\Command;

class GenerateVerifiedCurrentAffairsQuestions extends Command
{
    protected $signature = 'mci:current-affairs-generate-verified
        {--limit=20 : Maximum approved items to process (1-100)}
        {--dry-run : Validate eligible facts without creating questions}';

    protected $description = 'Generate bilingual questions only from high-confidence verified current-affairs facts';

    public function handle(VerifiedCurrentAffairsQuestionGenerator $generator): int
    {
        $result = $generator->generate((int) $this->option('limit'), (bool) $this->option('dry-run'));

        $this->info(sprintf(
            'Verified generation complete: generated=%d skipped=%d failed=%d%s',
            $result['generated'],
            $result['skipped'],
            $result['failed'],
            $this->option('dry-run') ? ' [dry-run]' : ''
        ));

        return $result['failed'] === 0 ? self::SUCCESS : self::FAILURE;
    }
}
