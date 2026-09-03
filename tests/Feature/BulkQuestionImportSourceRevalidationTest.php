<?php

namespace Tests\Feature;

use App\Models\ContentSource;
use App\Models\QuestionImportBatch;
use App\Services\BulkQuestionImportService;
use App\Services\QuestionIngestionService;
use App\Services\TrustedSourcePolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class BulkQuestionImportSourceRevalidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_stops_between_chunks_when_source_loses_approval(): void
    {
        $source = ContentSource::create([
            'name' => 'Official Test Source',
            'slug' => 'official-test-source-'.uniqid(),
            'source_type' => 'official',
            'base_url' => 'https://example.test',
            'trust_score' => 100,
            'allow_current_affairs' => true,
            'allow_question_generation' => true,
            'auto_publish_allowed' => false,
            'license_note' => 'Official public examination material.',
            'usage_notes' => 'Test source.',
            'is_active' => true,
        ]);

        $policy = Mockery::mock(TrustedSourcePolicy::class);
        $policy->shouldReceive('canGenerateQuestions')
            ->times(3)
            ->andReturn(true, true, false);

        $batch = new QuestionImportBatch([
            'accepted_count' => 1,
            'duplicate_count' => 0,
            'rejected_count' => 0,
        ]);

        $ingestion = Mockery::mock(QuestionIngestionService::class);
        $ingestion->shouldReceive('ingest')
            ->once()
            ->andReturn($batch);

        $service = new BulkQuestionImportService($ingestion, $policy);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            'Source is no longer approved by the MCI trusted-source policy.'
        );

        $service->importJsonFile(
            base_path('tests/Fixtures/two-question-chunks.json'),
            $source,
            1
        );
    }
}
