<?php

namespace Tests\Feature;

use App\Models\ContentSource;
use App\Models\ContentSourceCheck;
use Database\Seeders\ContentSourceSeeder;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TrustedSourceRetentionTest extends TestCase
{
    use RefreshDatabase;

    public function test_old_health_checks_are_pruned_and_recent_checks_are_retained(): void
    {
        $this->seed(ContentSourceSeeder::class);
        $source = ContentSource::query()->firstOrFail();

        $old = ContentSourceCheck::query()->create([
            'content_source_id' => $source->id,
            'healthy' => true,
            'reason' => 'retention-test',
            'checked_at' => now()->subDays(91),
        ]);

        $recent = ContentSourceCheck::query()->create([
            'content_source_id' => $source->id,
            'healthy' => true,
            'reason' => 'retention-test',
            'checked_at' => now()->subDays(89),
        ]);

        $this->artisan('question-bank:prune-source-checks', ['--days' => 90])
            ->expectsOutputToContain('Deleted 1 trusted source health check')
            ->assertSuccessful();

        $this->assertDatabaseMissing('content_source_checks', ['id' => $old->id]);
        $this->assertDatabaseHas('content_source_checks', ['id' => $recent->id]);
    }

    public function test_dry_run_reports_without_deleting_history(): void
    {
        $this->seed(ContentSourceSeeder::class);
        $source = ContentSource::query()->firstOrFail();

        $check = ContentSourceCheck::query()->create([
            'content_source_id' => $source->id,
            'healthy' => false,
            'reason' => 'retention-test',
            'checked_at' => now()->subDays(120),
        ]);

        $this->artisan('question-bank:prune-source-checks', [
            '--days' => 90,
            '--dry-run' => true,
        ])
            ->expectsOutputToContain('Dry run: 1 health check')
            ->assertSuccessful();

        $this->assertDatabaseHas('content_source_checks', ['id' => $check->id]);
    }

    public function test_retention_cleanup_is_scheduled_weekly(): void
    {
        $event = collect(app(Schedule::class)->events())->first(
            fn ($event) => str_contains($event->command ?? '', 'question-bank:prune-source-checks --days=90')
        );

        $this->assertNotNull($event);
        $this->assertSame('20 2 * * 0', $event->expression);
        $this->assertSame('Asia/Kolkata', $event->timezone);
    }
}
