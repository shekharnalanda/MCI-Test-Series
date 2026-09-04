<?php

namespace App\Console\Commands;

use App\Models\ContentSource;
use App\Services\OfficialCurrentAffairsFeedService;
use Illuminate\Console\Command;

class FetchOfficialCurrentAffairs extends Command
{
    protected $signature = 'mci:current-affairs-fetch
        {--source=* : Restrict fetching to one or more source slugs}
        {--limit=100 : Maximum feed items per source (1-500)}
        {--dry-run : Fetch and validate without writing items}';

    protected $description = 'Fetch current affairs from registered trusted official RSS/Atom feeds';

    public function handle(OfficialCurrentAffairsFeedService $feeds): int
    {
        $limit = max(1, min((int) $this->option('limit'), 500));
        $slugs = array_filter((array) $this->option('source'));

        $query = ContentSource::query()
            ->where('is_active', true)
            ->where('allow_current_affairs', true)
            ->whereNotNull('feed_url');

        if ($slugs !== []) {
            $query->whereIn('slug', $slugs);
        }

        $sources = $query->orderBy('slug')->get();

        if ($sources->isEmpty()) {
            $this->warn('No matching official feeds are enabled.');
            return self::SUCCESS;
        }

        $failed = 0;

        foreach ($sources as $source) {
            try {
                $result = $feeds->fetch($source, $limit, (bool) $this->option('dry-run'));
                $this->info(sprintf(
                    '%s: fetched=%d accepted=%d duplicates=%d rejected=%d%s',
                    $source->slug,
                    $result['fetched'],
                    $result['accepted'],
                    $result['duplicates'],
                    $result['rejected'],
                    $this->option('dry-run') ? ' [dry-run]' : ''
                ));
            } catch (\Throwable $exception) {
                $failed++;
                $this->error("{$source->slug}: {$exception->getMessage()}");
            }
        }

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }
}
