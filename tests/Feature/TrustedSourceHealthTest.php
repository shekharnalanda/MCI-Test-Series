<?php
namespace Tests\Feature;
use App\Models\ContentSource;
use App\Services\TrustedSourceHealthService;
use Database\Seeders\ContentSourceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;
class TrustedSourceHealthTest extends TestCase
{
    use RefreshDatabase;
    public function test_successful_check_updates_health_timestamps(): void
    {
        Http::fake(['https://official.example/*' => Http::response('ok', 200)]);
        $source = $this->source();
        $result = app(TrustedSourceHealthService::class)->check($source);
        $this->assertTrue($result['healthy']);
        $this->assertSame(200, $result['status']);
        $this->assertNotNull($source->fresh()->last_checked_at);
        $this->assertNotNull($source->fresh()->last_success_at);
    }
    public function test_failed_check_does_not_create_success_timestamp(): void
    {
        Http::fake(['https://official.example/*' => Http::response('down', 503)]);
        $source = $this->source();
        $result = app(TrustedSourceHealthService::class)->check($source);
        $this->assertFalse($result['healthy']);
        $this->assertSame(503, $result['status']);
        $this->assertNotNull($source->fresh()->last_checked_at);
        $this->assertNull($source->fresh()->last_success_at);
    }
    public function test_insecure_url_is_rejected_without_network_request(): void
    {
        Http::fake();
        $source = $this->source(['base_url' => 'http://official.example']);
        $result = app(TrustedSourceHealthService::class)->check($source);
        $this->assertFalse($result['healthy']);
        $this->assertSame('invalid_https_url', $result['reason']);
        Http::assertNothingSent();
    }
    public function test_health_command_checks_seeded_registry(): void
    {
        $this->seed(ContentSourceSeeder::class);
        Http::fake(['*' => Http::response('ok', 200)]);
        $this->artisan('question-bank:check-sources --fail-on-error')->assertSuccessful();
        $this->assertSame(7, ContentSource::query()->whereNotNull('last_checked_at')->count());
    }
    private function source(array $overrides = []): ContentSource
    {
        return ContentSource::create(array_merge([
            'name' => 'Official Health Source', 'slug' => 'official-health-source-'.uniqid(),
            'source_type' => 'official', 'base_url' => 'https://official.example/status',
            'feed_url' => null, 'trust_score' => 100, 'allow_current_affairs' => true,
            'allow_question_generation' => true, 'auto_publish_allowed' => false,
            'license_note' => 'Official public factual information.', 'usage_notes' => 'Health test.', 'is_active' => true,
        ], $overrides));
    }
}
