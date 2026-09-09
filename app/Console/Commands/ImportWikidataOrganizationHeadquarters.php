<?php

namespace App\Console\Commands;

use App\Services\WikidataOrganizationHeadquartersImporter;
use Illuminate\Console\Command;

class ImportWikidataOrganizationHeadquarters extends Command
{
    protected $signature = 'mci:wikidata-organization-headquarters
        {--limit=500 : Maximum source rows (10-500)}
        {--dry-run : Fetch and validate without writing questions}';

    protected $description = 'Import bilingual international-organization headquarters facts from CC0 Wikidata data';

    public function handle(WikidataOrganizationHeadquartersImporter $importer): int
    {
        try {
            $result = $importer->import((int) $this->option('limit'), (bool) $this->option('dry-run'));
            $this->info(sprintf(
                'Wikidata organization headquarters: fetched=%d accepted=%d duplicates=%d rejected=%d%s',
                $result['fetched'], $result['accepted'], $result['duplicates'], $result['rejected'],
                $result['dry_run'] ? ' [dry-run]' : ''
            ));

            return self::SUCCESS;
        } catch (\Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }
}
