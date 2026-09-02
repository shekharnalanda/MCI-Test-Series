<?php

namespace Tests\Feature;

use App\Models\ContentSource;
use App\Services\TrustedSourceHealthService;
use App\Services\TrustedSourcePolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TrustedSourceQuarantineTest extends TestCase
{
    use RefreshDatabase;

    public function test_external_source_is_quarantined_after_three_consecutive_failures(): void
    {
        $source = $this->source(['base_url' => 'http://insecure.example/status']);
        $service = app(TrustedSourceHealthService::class);

        $service->check($source);
        $service->check($source->fresh());
        $service->check($source->fresh());

        $source->refresh();

        $this->assertTrue($source->is_quarantined);
        $this->assertNotNull($source->quarantined_at);
        $this->assertSame('invalid_https_url', $source->quarantine_reason);

        $audit = app(TrustedSourcePolicy::class)->audit($source);
        $this->assertFalse($audit['trusted_for_questions']);
        $this->assertContains('source_quarantined', $audit['reasons']);
    }

    public function test_successful_health_check_recovers_quarantined_source(): void
    {
        $source = $this->source([
            'is_quarantined' => true,
            'quarantined_at' => now()->subHour(),
            'quarantine_reason' => 'connection_error',
        ]);

        Http::fake([
            'https://official.example/*' => Http::response(['ok' => true], 200),
        ]);

        app(TrustedSourceHealthService::class)->check($source);
        $source->refresh();

        $this->assertFalse($source->is_quarantined);
        $this->assertNull($source->quarantined_at);
        $this->assertNull($source->quarantine_reason);
    }

    public function test_internal_source_is_never_network_quarantined(): void
    {
        $source = $this->source([
            'source_type' => 'internal',
            'base_url' => null,
        ]);

        $service = app(TrustedSourceHealthService::class);
        $service->check($source);
        $service->check($source->fresh());
        $service->check($source->fresh());

        $this->assertFalse($source->fresh()->is_quarantined);
    }

    private function source(array $overrides = []): ContentSource
    {
        return ContentSource::query()->create(array_merge([
            'name' => 'Quarantine test source',
            'slug' => 'quarantine-source-'.uniqid(),
            'source_type' => 'official',
            'base_url' => 'https://official.example/status',
            'feed_url' => null,
            'trust_score' => 100,
            'allow_current_affairs' => true,
            'allow_question_generation' => true,
            'auto_publish_allowed' => false,
            'license_note' => 'Official public factual information.',
            'usage_notes' => 'Quarantine test.',
            'is_active' => true,
        ], $overrides));
    }
}
