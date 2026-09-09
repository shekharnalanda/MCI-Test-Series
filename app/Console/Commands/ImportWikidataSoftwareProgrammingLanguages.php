<?php

namespace App\Console\Commands;

use App\Services\WikidataSoftwareProgrammingLanguageImporter;
use Illuminate\Console\Command;

class ImportWikidataSoftwareProgrammingLanguages extends Command
{
    protected $signature = 'mci:wikidata-software-programming-languages {--limit=500 : Maximum source rows (10-500)} {--dry-run : Validate without writing}';
    protected $description = 'Import bilingual software programming-language facts from CC0 Wikidata data';

    public function handle(WikidataSoftwareProgrammingLanguageImporter $importer): int
    {
        try {
            $result = $importer->import((int) $this->option('limit'), (bool) $this->option('dry-run'));
            $this->info(sprintf('Wikidata software programming languages: fetched=%d accepted=%d duplicates=%d rejected=%d%s',
                $result['fetched'], $result['accepted'], $result['duplicates'], $result['rejected'], $result['dry_run'] ? ' [dry-run]' : ''));

            return self::SUCCESS;
        } catch (\Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }
}
