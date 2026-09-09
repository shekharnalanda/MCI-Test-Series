<?php

namespace App\Console\Commands;

use App\Services\WikidataAwardInceptionYearImporter;
use Illuminate\Console\Command;

class ImportWikidataAwardInceptionYears extends Command
{
    protected $signature = 'mci:wikidata-award-inception-years {--limit=500 : Maximum source rows (10-500)} {--dry-run : Validate without writing}';
    protected $description = 'Import bilingual award inception-year facts from CC0 Wikidata data';

    public function handle(WikidataAwardInceptionYearImporter $importer): int
    {
        try {
            $result = $importer->import((int) $this->option('limit'), (bool) $this->option('dry-run'));
            $this->info(sprintf('Wikidata award inception years: fetched=%d accepted=%d duplicates=%d rejected=%d%s',
                $result['fetched'], $result['accepted'], $result['duplicates'], $result['rejected'], $result['dry_run'] ? ' [dry-run]' : ''));

            return self::SUCCESS;
        } catch (\Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }
}
