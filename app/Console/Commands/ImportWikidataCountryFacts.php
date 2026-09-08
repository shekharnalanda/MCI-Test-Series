<?php

namespace App\Console\Commands;

use App\Services\WikidataCountryFactImporter;
use Illuminate\Console\Command;

class ImportWikidataCountryFacts extends Command
{
    protected $signature = 'mci:wikidata-country-facts
        {--family=all : currency, continent, or all}
        {--limit=300 : Maximum source rows per family (10-500)}
        {--dry-run : Fetch and validate without writing questions}';

    protected $description = 'Import unambiguous bilingual country facts from CC0 Wikidata data';

    public function handle(WikidataCountryFactImporter $importer): int
    {
        $family = (string) $this->option('family');
        $families = $family === 'all' ? ['currency', 'continent'] : [$family];

        foreach ($families as $current) {
            try {
                $result = $importer->import($current, (int) $this->option('limit'), (bool) $this->option('dry-run'));
                $this->info(sprintf(
                    'Wikidata %s: fetched=%d accepted=%d duplicates=%d rejected=%d%s',
                    $current,
                    $result['fetched'],
                    $result['accepted'],
                    $result['duplicates'],
                    $result['rejected'],
                    $result['dry_run'] ? ' [dry-run]' : ''
                ));
            } catch (\Throwable $exception) {
                $this->error($current.': '.$exception->getMessage());

                return self::FAILURE;
            }
        }

        return self::SUCCESS;
    }
}
