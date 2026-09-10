<?php

namespace App\Console\Commands;

use App\Services\WikidataElementOrderHardQuestionImporter;
use Illuminate\Console\Command;

class ImportWikidataElementOrderHardQuestions extends Command
{
    protected $signature = 'mci:wikidata-element-order-hard {--limit=10 : Questions to generate (1-25)} {--dry-run : Validate without writing}';
    protected $description = 'Import hard bilingual element-order questions from CC0 Wikidata facts';

    public function handle(WikidataElementOrderHardQuestionImporter $importer): int
    {
        try {
            $result = $importer->import((int) $this->option('limit'), (bool) $this->option('dry-run'));
            $this->info(sprintf('Wikidata hard element order: fetched=%d accepted=%d duplicates=%d rejected=%d%s',
                $result['fetched'], $result['accepted'], $result['duplicates'], $result['rejected'],
                $result['dry_run'] ? ' [dry-run]' : ''));

            return self::SUCCESS;
        } catch (\Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }
}
