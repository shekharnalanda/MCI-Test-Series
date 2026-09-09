<?php

namespace App\Console\Commands;

use App\Services\WikidataStadiumCountryImporter;
use Illuminate\Console\Command;

class ImportWikidataStadiumCountries extends Command
{
    protected $signature = 'mci:wikidata-stadium-countries {--limit=500 : Maximum source rows (10-500)} {--dry-run : Validate without writing}';
    protected $description = 'Import bilingual stadium-country facts from CC0 Wikidata data';

    public function handle(WikidataStadiumCountryImporter $importer): int
    {
        try {
            $result = $importer->import((int) $this->option('limit'), (bool) $this->option('dry-run'));
            $this->info(sprintf('Wikidata stadium countries: fetched=%d accepted=%d duplicates=%d rejected=%d%s',
                $result['fetched'], $result['accepted'], $result['duplicates'], $result['rejected'], $result['dry_run'] ? ' [dry-run]' : ''));

            return self::SUCCESS;
        } catch (\Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }
}
