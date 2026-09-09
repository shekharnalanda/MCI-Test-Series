<?php

namespace App\Console\Commands;

use App\Services\WikidataOrganizationInceptionYearImporter;
use Illuminate\Console\Command;

class ImportWikidataOrganizationInceptionYears extends Command
{
    protected $signature = 'mci:wikidata-organization-inception-years
        {--limit=500 : Maximum source rows (10-500)}
        {--dry-run : Fetch and validate without writing questions}';

    protected $description = 'Import bilingual international-organization inception-year facts from CC0 Wikidata data';

    public function handle(WikidataOrganizationInceptionYearImporter $importer): int
    {
        try {
            $result = $importer->import((int) $this->option('limit'), (bool) $this->option('dry-run'));
            $this->info(sprintf(
                'Wikidata organization inception years: fetched=%d accepted=%d duplicates=%d rejected=%d%s',
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
