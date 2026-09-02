<?php

namespace Tests\Feature;

use App\Models\ContentSource;
use App\Models\ContentSourceCheck;
use App\Services\TrustedSourceHealthService;
use Database\Seeders\ContentSourceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TrustedSourceHealthTest extends TestCase
{
    use RefreshDatabase;

    public function test_successful_check_updates_timestamps_and_history(): void
    {
        Http::fake(['https://official.example/*' => Http::response('ok', 200)]);

        $source = $this->source();
        $result = app(TrustedSourceHealthService::class)->check($source);

        $this->assertTrue($result['healthy']);
        $this->assertSame(200, $result['status']);
        $this->assertNotNull($source->fresh()->last_checked_at);
        $this->assertNotNull($source->fresh()->last_success_at);
        $this->assertDatabaseHas('content_source_checks', [
            'content_source_id' => $source->id,
            'healthy' => true,
            'http_status' => 200,
            'reason' => 'reachable',
        ]);
    }

    public function test_failed_check_is_written_to_history(): void
    {
        Http::fake(['https://official.example/*' => Http::response('down', 503)]);

        $source = $this->source();
        $result = app(TrustedSourceHealthService::class)->check($source);

        $this->assertFalse($result['healthy']);
        $this->assertSame(503, $result['status']);
        $this->assertNotNull($source->fresh()->last_checked_at);
        $this->assertNull($source->fresh()->last_success_at);
        $this->assertDatabaseHas('content_source_checks', [
            'content_source_id' => $source->id,
            'healthy' => false,
            'http_status' => 503,
            'reason' => 'http_error',
        ]);
    }

    public function test_insecure_url_is_logged_without_network_request(): void
    {
        Http::fake();

        $source = $this->source(['base_url' => 'http://official.example']);
        $result = app(TrustedSourceHealthService::class)->check($source);

        $this->assertFalse($result['healthy']);
        $this->assertSame('invalid_https_url', $result['reason']);
        $this->assertDatabaseHas('content_source_checks', [
            'content_source_id' => $source->id,
            'reason' => 'invalid_https_url',
        ]);
        Http::assertNothingSent();
    }

    public function test_health_command_records_one_check_per_active_source(): void
    {
        $this->seed(ContentSourceSeeder::class);
        Http::fake(['*' => Http::response('ok', 200)]);

        $this->artisan('question-bank:check-sources --fail-on-error')
            ->assertSuccessful();

        $this->assertSame(7, ContentSource::whereNotNull('last_checked_at')->count());
        $this->assertSame(7, ContentSourceCheck::count());
    }

    private function source(array $overrides = []): ContentSource
    {
        return ContentSource::create(array_merge([
            'name' => 'Official Health Source',
            'slug' => 'official-health-source-'.uniqid(),
            'source_type' => 'official',
            'base_url' => 'https://official.example/status',
            'feed_url' => null,
            'trust_score' => 100,
            'allow_current_affairs' => true,
            'allow_question_generation' => true,
            'auto_publish_allowed' => false,
            'license_note' => 'Official public factual information.',
            'usage_notes' => 'Health test.',
            'is_active' => true,
        ], $overrides));
    }
}
