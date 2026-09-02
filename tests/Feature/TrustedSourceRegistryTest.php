<?php

namespace Tests\Feature;

use App\Models\ContentSource;
use App\Services\TrustedSourcePolicy;
use Database\Seeders\ContentSourceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TrustedSourceRegistryTest extends TestCase
{
    use RefreshDatabase;

    public function test_registry_seeds_only_policy_compliant_enabled_sources(): void
    {
        $this->seed(ContentSourceSeeder::class);

        $policy = app(TrustedSourcePolicy::class);
        $enabled = ContentSource::query()
            ->where('is_active', true)
            ->where('allow_question_generation', true)
            ->get();

        $this->assertCount(7, $enabled);

        foreach ($enabled as $source) {
            $this->assertTrue(
                $policy->canGenerateQuestions($source),
                $source->name.' failed trusted-source policy.'
            );
        }
    }

    public function test_only_high_trust_government_sources_are_auto_publish_candidates(): void
    {
        $this->seed(ContentSourceSeeder::class);

        $policy = app(TrustedSourcePolicy::class);
        $autoPublish = ContentSource::query()
            ->where('auto_publish_allowed', true)
            ->get();

        $this->assertEqualsCanonicalizing(
            ['press-information-bureau', 'reserve-bank-of-india'],
            $autoPublish->pluck('slug')->all()
        );

        foreach ($autoPublish as $source) {
            $this->assertTrue($policy->canAutoPublishCurrentAffairs($source));
        }
    }

    public function test_unreviewed_open_knowledge_template_stays_inactive(): void
    {
        $this->seed(ContentSourceSeeder::class);

        $source = ContentSource::where('slug', 'open-knowledge-candidate')->firstOrFail();

        $this->assertFalse($source->is_active);
        $this->assertFalse($source->allow_question_generation);
        $this->assertFalse($source->auto_publish_allowed);
    }
}
