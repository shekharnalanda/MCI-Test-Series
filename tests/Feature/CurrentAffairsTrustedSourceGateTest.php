<?php

namespace Tests\Feature;

use App\Models\ContentSource;
use App\Models\CurrentAffairItem;
use App\Services\CurrentAffairsService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CurrentAffairsTrustedSourceGateTest extends TestCase
{
    use RefreshDatabase;

    public function test_quarantined_source_cannot_auto_publish(): void
    {
        $this->seed(DatabaseSeeder::class);

        $source = ContentSource::query()->firstOrFail();
        $source->update([
            'is_active' => true,
            'allow_current_affairs' => true,
            'auto_publish_allowed' => true,
            'trust_score' => 100,
            'is_quarantined' => true,
            'quarantined_at' => now(),
        ]);

        $result = app(CurrentAffairsService::class)->ingest(
            $source->fresh(),
            [[
                'title' => 'Verified national examination update announced',
                'summary' => 'A sufficiently detailed verified summary that meets the automatic quality threshold for publication safety testing.',
                'source_url' => 'https://example.test/quarantined-source',
                'published_at' => now(),
            ]]
        );

        $item = CurrentAffairItem::query()->latest('id')->firstOrFail();

        $this->assertSame(1, $result['accepted']);
        $this->assertSame('pending', $item->status);
        $this->assertFalse($item->auto_approved);
    }
}
