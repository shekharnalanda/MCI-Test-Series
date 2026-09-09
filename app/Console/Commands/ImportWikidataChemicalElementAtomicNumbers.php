<?php

namespace App\Console\Commands;

use App\Services\WikidataChemicalElementAtomicNumberImporter;
use Illuminate\Console\Command;

class ImportWikidataChemicalElementAtomicNumbers extends Command
{
    protected $signature = 'mci:wikidata-element-atomic-numbers {--limit=250 : Maximum source rows (10-250)} {--dry-run : Validate without writing}';
    protected $description = 'Import bilingual chemical-element atomic-number facts from CC0 Wikidata data';

    public function handle(WikidataChemicalElementAtomicNumberImporter $importer): int
    {
        try {
            $result = $importer->import((int) $this->option('limit'), (bool) $this->option('dry-run'));
            $this->info(sprintf('Wikidata element atomic numbers: fetched=%d accepted=%d duplicates=%d rejected=%d%s',
                $result['fetched'], $result['accepted'], $result['duplicates'], $result['rejected'], $result['dry_run'] ? ' [dry-run]' : ''));

            return self::SUCCESS;
        } catch (\Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }
}
