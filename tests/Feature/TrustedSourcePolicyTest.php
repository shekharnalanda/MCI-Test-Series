<?php

namespace Tests\Feature;

use App\Models\ContentSource;
use App\Services\TrustedSourcePolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TrustedSourcePolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_verified_official_https_source_can_feed_question_bank(): void
    {
        $source = ContentSource::create($this->sourceData());

        $this->assertTrue(app(TrustedSourcePolicy::class)->canGenerateQuestions($source));
    }

    public function test_insecure_or_low_trust_source_is_rejected(): void
    {
        $source = ContentSource::create($this->sourceData([
            'base_url' => 'http://example.test',
            'trust_score' => 70,
        ]));

        $audit = app(TrustedSourcePolicy::class)->audit($source);

        $this->assertFalse($audit['trusted_for_questions']);
        $this->assertContains('secure_base_url_required', $audit['reasons']);
        $this->assertContains('trust_score_below_90', $audit['reasons']);
    }

    public function test_open_knowledge_requires_explicit_open_license_evidence(): void
    {
        $source = ContentSource::create($this->sourceData([
            'source_type' => 'open_knowledge',
            'license_note' => 'Reusable content.',
        ]));

        $audit = app(TrustedSourcePolicy::class)->audit($source);

        $this->assertFalse($audit['trusted_for_questions']);
        $this->assertContains('open_license_evidence_required', $audit['reasons']);
    }

    public function test_bulk_import_refuses_untrusted_source_before_reading_file(): void
    {
        $source = ContentSource::create($this->sourceData([
            'trust_score' => 40,
        ]));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Source is not approved');

        app(\App\Services\BulkQuestionImportService::class)
            ->importJsonFile('/missing.json', $source);
    }

    private function sourceData(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Official Test Source',
            'slug' => 'official-test-source-'.uniqid(),
            'source_type' => 'official',
            'base_url' => 'https://example.test',
            'feed_url' => null,
            'trust_score' => 100,
            'allow_current_affairs' => true,
            'allow_question_generation' => true,
            'auto_publish_allowed' => false,
            'license_note' => 'Official public examination material.',
            'usage_notes' => 'Test source.',
            'is_active' => true,
        ], $overrides);
    }
}
