<?php

namespace App\Console\Commands;

use App\Services\WikidataSoftwareImporter;
use Illuminate\Console\Command;

class ImportWikidataSoftware extends Command
{
    protected $signature = 'mci:wikidata-software
        {--limit=500 : Maximum source rows (10-500)}
        {--dry-run : Fetch and validate without writing questions}';

    protected $description = 'Import unambiguous bilingual software developer facts from CC0 Wikidata data';

    public function handle(WikidataSoftwareImporter $importer): int
    {
        try {
            $result = $importer->import((int) $this->option('limit'), (bool) $this->option('dry-run'));
            $this->info(sprintf(
                'Wikidata software: fetched=%d accepted=%d duplicates=%d rejected=%d%s',
                $result['fetched'],
                $result['accepted'],
                $result['duplicates'],
                $result['rejected'],
                $result['dry_run'] ? ' [dry-run]' : ''
            ));

            return self::SUCCESS;
        } catch (\Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }
}
