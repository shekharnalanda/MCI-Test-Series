<?php

namespace App\Console\Commands;

use App\Services\WikidataCountryCapitalImporter;
use Illuminate\Console\Command;

class ImportWikidataCountryCapitals extends Command
{
    protected $signature = 'mci:wikidata-capitals
        {--limit=100 : Maximum complete bilingual facts (10-200)}
        {--dry-run : Fetch and validate without writing questions}';

    protected $description = 'Import original bilingual country-capital questions from CC0 Wikidata facts';

    public function handle(WikidataCountryCapitalImporter $importer): int
    {
        try {
            $result = $importer->import((int) $this->option('limit'), (bool) $this->option('dry-run'));

            $this->info(sprintf(
                'Wikidata capitals: fetched=%d accepted=%d duplicates=%d rejected=%d%s',
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
