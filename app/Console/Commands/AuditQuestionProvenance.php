<?php

namespace App\Console\Commands;

use App\Models\Question;
use Illuminate\Console\Command;

class AuditQuestionProvenance extends Command
{
    protected $signature = 'question-bank:audit-provenance
                            {--strict : Fail when external questions lack required provenance}';

    protected $description = 'Audit source traceability for externally sourced questions';

    public function handle(): int
    {
        $external = Question::query()
            ->whereNotNull('content_source_id')
            ->whereHas('source', fn ($query) => $query->where('source_type', '!=', 'internal'));

        $total = (clone $external)->count();
        $missingUrl = (clone $external)->whereNull('source_url')->count();
        $missingPublishedAt = (clone $external)->whereNull('source_published_at')->count();
        $complete = (clone $external)
            ->whereNotNull('source_url')
            ->whereNotNull('source_published_at')
            ->count();

        $this->table(
            ['External questions', 'Complete', 'Missing URL', 'Missing publication date'],
            [[$total, $complete, $missingUrl, $missingPublishedAt]],
        );

        if ($this->option('strict') && ($missingUrl > 0 || $missingPublishedAt > 0)) {
            $this->error('Question provenance audit failed.');

            return self::FAILURE;
        }

        $this->info('Question provenance audit passed.');

        return self::SUCCESS;
    }
}
