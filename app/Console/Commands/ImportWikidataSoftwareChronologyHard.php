<?php

namespace App\Console\Commands;

use App\Services\WikidataSoftwareChronologyHardImporter;
use Illuminate\Console\Command;

class ImportWikidataSoftwareChronologyHard extends Command
{
    protected $signature = 'mci:wikidata-software-chronology-hard {--limit=15 : Questions (1-25)} {--dry-run : Validate without writing}';
    protected $description = 'Build hard bilingual software chronology questions from verified Wikidata facts';

    public function handle(WikidataSoftwareChronologyHardImporter $importer): int
    {
        try {
            $result = $importer->import((int) $this->option('limit'), (bool) $this->option('dry-run'));
            $this->info(sprintf('Wikidata hard software chronology: fetched=%d accepted=%d duplicates=%d rejected=%d%s',
                $result['fetched'], $result['accepted'], $result['duplicates'], $result['rejected'],
                $result['dry_run'] ? ' [dry-run]' : ''));
            return self::SUCCESS;
        } catch (\Throwable $exception) {
            $this->error($exception->getMessage());
            return self::FAILURE;
        }
    }
}
