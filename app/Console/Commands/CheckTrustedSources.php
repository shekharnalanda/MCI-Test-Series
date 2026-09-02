<?php
namespace App\Console\Commands;
use App\Models\ContentSource;
use App\Services\TrustedSourceHealthService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;
class CheckTrustedSources extends Command
{
    protected $signature = 'question-bank:check-sources {--slug= : Check one registered source} {--fail-on-error : Return failure when any source is unhealthy}';
    protected $description = 'Check availability of registered MCI trusted sources';
    public function handle(TrustedSourceHealthService $health): int
    {
        if (! Schema::hasTable('content_sources')) {
            $this->warn('Content sources are not migrated. Run database migrations first.');
            return self::FAILURE;
        }
        $query = ContentSource::query()->where('is_active', true);
        if ($slug = $this->option('slug')) {
            $query->where('slug', $slug);
        }
        $sources = $query->orderBy('name')->get();
        if ($sources->isEmpty()) {
            $this->warn('No matching active source was found.');
            return self::FAILURE;
        }
        $rows = [];
        $failures = 0;
        foreach ($sources as $source) {
            $result = $health->check($source);
            if (! $result['healthy']) {
                $failures++;
            }
            $rows[] = [$source->name, $source->base_url ?: 'internal', $result['healthy'] ? 'HEALTHY' : 'FAILED', $result['status'] ?? '-', $result['reason']];
        }
        $this->table(['Source', 'URL', 'Health', 'HTTP', 'Result'], $rows);
        if ($failures > 0) {
            $this->warn("{$failures} source(s) are currently unhealthy.");
            return $this->option('fail-on-error') ? self::FAILURE : self::SUCCESS;
        }
        $this->info('All checked sources are healthy.');
        return self::SUCCESS;
    }
}
