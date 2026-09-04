<?php

namespace Tests\Feature;

use App\Models\ContentSource;
use App\Models\ContentSourceCheck;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OfficialCurrentAffairsFeedTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_imports_only_links_on_the_registered_official_domain(): void
    {
        $source = ContentSource::create([
            'name' => 'Official Test Source',
            'slug' => 'official-test',
            'source_type' => 'government',
            'base_url' => 'https://official.gov.in',
            'feed_url' => 'https://official.gov.in/releases.xml',
            'trust_score' => 100,
            'allow_current_affairs' => true,
            'allow_question_generation' => true,
            'auto_publish_allowed' => true,
            'license_note' => 'Official public facts; original questions only.',
            'is_active' => true,
        ]);

        ContentSourceCheck::create([
            'content_source_id' => $source->id,
            'checked_at' => now(),
            'healthy' => true,
            'http_status' => 200,
            'reason' => 'ok',
        ]);

        Http::fake(['https://official.gov.in/releases.xml' => Http::response(<<<'XML'
            <rss><channel>
              <item><title>Official policy update for competitive examinations</title><description>This sufficiently detailed official summary contains factual context for safe question preparation.</description><link>https://official.gov.in/release/1</link><guid>release-1</guid><pubDate>Thu, 03 Sep 2026 10:00:00 GMT</pubDate></item>
              <item><title>Injected external item must be rejected</title><description>This item points outside the registered official domain and must never be imported.</description><link>https://example.com/injected</link><pubDate>Thu, 03 Sep 2026 10:00:00 GMT</pubDate></item>
            </channel></rss>
        XML, 200, ['Content-Type' => 'application/rss+xml'])]);

        $this->artisan('mci:current-affairs-fetch', ['--source' => ['official-test']])
            ->assertSuccessful();

        $this->assertDatabaseCount('current_affair_items', 1);
        $this->assertDatabaseHas('current_affair_items', [
            'content_source_id' => $source->id,
            'source_url' => 'https://official.gov.in/release/1',
            'status' => 'approved',
        ]);
    }

    public function test_dry_run_does_not_write_items(): void
    {
        $source = ContentSource::create([
            'name' => 'Internal Feed Test', 'slug' => 'internal-feed-test', 'source_type' => 'internal',
            'base_url' => 'https://official.gov.in', 'feed_url' => 'https://official.gov.in/releases.xml',
            'trust_score' => 100, 'allow_current_affairs' => true, 'allow_question_generation' => true,
            'auto_publish_allowed' => false, 'is_active' => true,
        ]);

        Http::fake(['*' => Http::response('<rss><channel><item><title>A valid official headline for dry run</title><link>https://official.gov.in/1</link></item></channel></rss>', 200)]);

        $this->artisan('mci:current-affairs-fetch', ['--source' => ['internal-feed-test'], '--dry-run' => true])
            ->assertSuccessful();

        $this->assertDatabaseCount('current_affair_items', 0);
    }
}
