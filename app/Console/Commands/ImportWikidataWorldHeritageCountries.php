<?php

namespace App\Console\Commands;

use App\Services\WikidataWorldHeritageCountryImporter;
use Illuminate\Console\Command;

class ImportWikidataWorldHeritageCountries extends Command
{
    protected $signature = 'mci:wikidata-world-heritage-countries {--limit=500 : Maximum source rows (10-500)} {--dry-run : Validate without writing}';
    protected $description = 'Import bilingual UNESCO World Heritage site-country facts from CC0 Wikidata data';

    public function handle(WikidataWorldHeritageCountryImporter $importer): int
    {
        try {
            $result = $importer->import((int) $this->option('limit'), (bool) $this->option('dry-run'));
            $this->info(sprintf('Wikidata World Heritage countries: fetched=%d accepted=%d duplicates=%d rejected=%d%s',
                $result['fetched'], $result['accepted'], $result['duplicates'], $result['rejected'], $result['dry_run'] ? ' [dry-run]' : ''));

            return self::SUCCESS;
        } catch (\Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }
}
