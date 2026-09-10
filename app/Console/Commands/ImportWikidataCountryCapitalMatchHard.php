<?php

namespace App\Console\Commands;

use App\Services\WikidataCountryCapitalMatchHardImporter;
use Illuminate\Console\Command;

class ImportWikidataCountryCapitalMatchHard extends Command
{
    protected $signature = 'mci:wikidata-country-capital-match-hard {--limit=15 : Questions (1-25)} {--dry-run : Validate without writing}';
    protected $description = 'Import hard bilingual country-capital matching questions from CC0 Wikidata facts';

    public function handle(WikidataCountryCapitalMatchHardImporter $importer): int
    {
        try {
            $result = $importer->import((int) $this->option('limit'), (bool) $this->option('dry-run'));
            $this->info(sprintf('Wikidata hard country-capital match: fetched=%d accepted=%d duplicates=%d rejected=%d%s',
                $result['fetched'], $result['accepted'], $result['duplicates'], $result['rejected'],
                $result['dry_run'] ? ' [dry-run]' : ''));

            return self::SUCCESS;
        } catch (\Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }
}
