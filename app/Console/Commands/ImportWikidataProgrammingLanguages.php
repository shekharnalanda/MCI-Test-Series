<?php

namespace App\Console\Commands;

use App\Services\WikidataProgrammingLanguageImporter;
use Illuminate\Console\Command;

class ImportWikidataProgrammingLanguages extends Command
{
    protected $signature = 'mci:wikidata-programming-languages
        {--limit=500 : Maximum source rows (10-500)}
        {--dry-run : Fetch and validate without writing questions}';

    protected $description = 'Import unambiguous bilingual programming-language designer facts from CC0 Wikidata data';

    public function handle(WikidataProgrammingLanguageImporter $importer): int
    {
        try {
            $result = $importer->import((int) $this->option('limit'), (bool) $this->option('dry-run'));
            $this->info(sprintf(
                'Wikidata programming languages: fetched=%d accepted=%d duplicates=%d rejected=%d%s',
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
