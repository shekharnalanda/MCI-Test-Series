<?php

namespace App\Console\Commands;

use App\Services\WikidataChemicalElementImporter;
use Illuminate\Console\Command;

class ImportWikidataChemicalElements extends Command
{
    protected $signature = 'mci:wikidata-elements
        {--limit=150 : Maximum complete bilingual elements (10-200)}
        {--dry-run : Fetch and validate without writing questions}';

    protected $description = 'Import bilingual periodic-table questions from CC0 Wikidata facts';

    public function handle(WikidataChemicalElementImporter $importer): int
    {
        try {
            $result = $importer->import((int) $this->option('limit'), (bool) $this->option('dry-run'));
            $this->info(sprintf(
                'Wikidata elements: facts=%d questions=%d accepted=%d duplicates=%d rejected=%d%s',
                $result['facts'],
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
