<?php

namespace Tests\Feature;

use App\Models\ContentSource;
use App\Models\ContentSourceCheck;
use App\Services\TrustedSourcePolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TrustedSourceHealthPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_recent_external_source_gets_initial_health_check_grace_period(): void
    {
        $source = $this->source();

        $audit = app(TrustedSourcePolicy::class)->audit($source);

        $this->assertTrue($audit['trusted_for_questions']);
        $this->assertNotContains('health_check_missing', $audit['reasons']);
    }

    public function test_old_external_source_without_health_history_is_blocked(): void
    {
        $source = $this->source();
        $source->forceFill(['created_at' => now()->subHours(25)])->save();

        $audit = app(TrustedSourcePolicy::class)->audit($source->fresh());

        $this->assertFalse($audit['trusted_for_questions']);
        $this->assertContains('health_check_missing', $audit['reasons']);
    }

    public function test_stale_or_failed_health_check_blocks_question_generation(): void
    {
        $stale = $this->source();
        $this->check($stale, true, now()->subHours(25), 'old success');

        $failed = $this->source();
        $this->check($failed, false, now()->subHour(), 'server error');

        $staleAudit = app(TrustedSourcePolicy::class)->audit($stale);
        $failedAudit = app(TrustedSourcePolicy::class)->audit($failed);

        $this->assertFalse($staleAudit['trusted_for_questions']);
        $this->assertContains('health_check_stale', $staleAudit['reasons']);
        $this->assertFalse($failedAudit['trusted_for_questions']);
        $this->assertContains('health_check_failed', $failedAudit['reasons']);
    }

    public function test_recent_successful_check_keeps_source_trusted(): void
    {
        $source = $this->source();
        $this->check($source, true, now()->subHour(), 'reachable');

        $audit = app(TrustedSourcePolicy::class)->audit($source);

        $this->assertTrue($audit['trusted_for_questions']);
        $this->assertEmpty(array_intersect(
            ['health_check_missing', 'health_check_stale', 'health_check_failed'],
            $audit['reasons'],
        ));
    }

    private function source(): ContentSource
    {
        return ContentSource::query()->create([
            'name' => 'Health-gated source',
            'slug' => 'health-gated-source-'.uniqid(),
            'source_type' => 'official',
            'base_url' => 'https://official.example/status',
            'feed_url' => null,
            'trust_score' => 100,
            'allow_current_affairs' => true,
            'allow_question_generation' => true,
            'auto_publish_allowed' => false,
            'license_note' => 'Official public factual information.',
            'usage_notes' => 'Health policy test.',
            'is_active' => true,
        ]);
    }

    private function check(ContentSource $source, bool $healthy, $checkedAt, string $reason): void
    {
        ContentSourceCheck::query()->create([
            'content_source_id' => $source->id,
            'healthy' => $healthy,
            'reason' => $reason,
            'checked_at' => $checkedAt,
        ]);
    }
}
