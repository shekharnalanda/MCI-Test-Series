<?php

namespace App\Console\Commands;

use App\Services\WikidataBookAuthorImporter;
use App\Services\WikidataCountryFactImporter;
use App\Services\WikidataDiscoveryImporter;
use App\Services\WikidataSoftwareImporter;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Throwable;

class RefreshQuestionBank extends Command
{
    protected $signature = 'question-bank:refresh {--limit=50 : Source rows per run (10-50)}';

    protected $description = 'Import one bounded country-fact page, rotating families without changing old questions';

    public function handle(WikidataCountryFactImporter $importer, WikidataDiscoveryImporter $discoveries, WikidataSoftwareImporter $software, WikidataBookAuthorImporter $books): int
    {
        $lock = Cache::lock('mci-question-bank-refresh', 300);
        if (! $lock->get()) {
            $this->info('Question bank refresh already running.');

            return self::SUCCESS;
        }
        try {
            $families = array_merge($importer::families(), ['discoveries', 'software', 'books']);
            $state = Cache::get('mci-question-bank-refresh-cursor', ['family' => 0, 'offsets' => []]);
            $index = (int) $state['family'] % count($families);
            $family = $families[$index];
            $offset = max(0, (int) ($state['offsets'][$family] ?? 0));
            $limit = max(10, min(50, (int) $this->option('limit')));
            $result = match ($family) {
                'discoveries' => $discoveries->import($limit, false, $offset),
                'software' => $software->import($limit, false, $offset),
                'books' => $books->import($limit, false, $offset),
                default => $importer->import($family, $limit, false, $offset),
            };
            $state['offsets'][$family] = $result['next_offset'];
            $state['family'] = ($index + 1) % count($families);
            Cache::forever('mci-question-bank-refresh-cursor', $state);
            $this->info(sprintf('Refresh %s offset=%d: fetched=%d accepted=%d duplicates=%d rejected=%d next_offset=%d',
                $family, $offset, $result['fetched'], $result['accepted'], $result['duplicates'], $result['rejected'], $result['next_offset']));

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->error('Refresh failed; cursor retained: '.$exception->getMessage());

            return self::FAILURE;
        } finally {
            $lock->release();
        }
    }
}
