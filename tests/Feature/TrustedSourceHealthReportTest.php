<?php

namespace Tests\Feature;

use App\Models\ContentSource;
use App\Models\ContentSourceCheck;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TrustedSourceHealthReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_report_summarizes_healthy_unhealthy_and_stale_sources(): void
    {
        $healthy = $this->source('Healthy source');
        $unhealthy = $this->source('Unhealthy source');
        $stale = $this->source('Stale source');

        $this->check($healthy, true, now()->subHour(), 'reachable');
        $this->check($unhealthy, false, now()->subHours(2), 'server error');
        $this->check($stale, true, now()->subHours(30), 'old success');

        $this->artisan('question-bank:source-health-report', ['--hours' => 24])
            ->expectsOutputToContain('Active sources: 3 | Healthy: 1 | Unhealthy: 1 | Stale/unchecked: 1')
            ->assertSuccessful();
    }

    public function test_strict_report_fails_for_unhealthy_or_unchecked_sources(): void
    {
        $this->source('Unchecked source');

        $this->artisan('question-bank:source-health-report', [
            '--hours' => 24,
            '--fail-on-unhealthy' => true,
        ])
            ->expectsOutputToContain('Trusted source health report failed.')
            ->assertFailed();
    }

    public function test_health_report_is_scheduled_after_daily_source_checks(): void
    {
        $event = collect(app(Schedule::class)->events())->first(
            fn ($event) => str_contains($event->command ?? '', 'question-bank:source-health-report')
        );

        $this->assertNotNull($event);
        $this->assertSame('10 2 * * *', $event->expression);
        $this->assertSame('Asia/Kolkata', $event->timezone);
    }

    private function source(string $name): ContentSource
    {
        return ContentSource::query()->create([
            'name' => $name,
            'slug' => str($name)->slug().'-'.uniqid(),
            'source_type' => 'official',
            'base_url' => 'https://official.example/status',
            'feed_url' => null,
            'trust_score' => 100,
            'allow_current_affairs' => true,
            'allow_question_generation' => true,
            'auto_publish_allowed' => false,
            'license_note' => 'Official public factual information.',
            'usage_notes' => 'Health report test.',
            'is_active' => true,
        ]);
    }

    private function check(ContentSource $source, bool $healthy, $checkedAt, string $reason): ContentSourceCheck
    {
        return ContentSourceCheck::query()->create([
            'content_source_id' => $source->id,
            'healthy' => $healthy,
            'reason' => $reason,
            'checked_at' => $checkedAt,
        ]);
    }
}
