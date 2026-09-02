<?php

namespace App\Console\Commands;

use App\Models\ContentSource;
use App\Services\TrustedSourcePolicy;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class AuditTrustedSources extends Command
{
    protected $signature = 'question-bank:audit-sources {--strict : Fail when any enabled source is not trusted}';

    protected $description = 'Audit Question Bank sources against MCI trusted-source policy';

    public function handle(TrustedSourcePolicy $policy): int
    {
        if (! Schema::hasTable('content_sources')) {
            $this->warn('Content sources are not migrated. Run database migrations first.');

            return self::FAILURE;
        }

        $sources = ContentSource::query()->orderBy('name')->get();

        if ($sources->isEmpty()) {
            $this->warn('No content sources are registered.');

            return self::FAILURE;
        }

        $rows = [];
        $invalid = 0;

        foreach ($sources as $source) {
            $audit = $policy->audit($source);
            $enabled = $source->is_active
                && ($source->allow_question_generation || $source->allow_current_affairs);

            if ($enabled && ! $audit['trusted_for_questions']) {
                $invalid++;
            }

            $rows[] = [
                $source->name,
                $source->source_type,
                $source->trust_score,
                $audit['trusted_for_questions'] ? 'YES' : 'NO',
                $audit['trusted_for_auto_publish'] ? 'YES' : 'NO',
                implode(', ', $audit['reasons']) ?: 'verified',
            ];
        }

        $this->table(
            ['Source', 'Type', 'Trust', 'Questions', 'Auto publish', 'Result'],
            $rows
        );

        if ($invalid > 0) {
            $this->error("{$invalid} enabled source(s) failed trusted-source policy.");

            return $this->option('strict') ? self::FAILURE : self::SUCCESS;
        }

        $this->info('All enabled sources passed trusted-source policy.');

        return self::SUCCESS;
    }
}
