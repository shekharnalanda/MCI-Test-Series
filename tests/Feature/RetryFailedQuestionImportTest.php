<?php

namespace Tests\Feature;

use App\Models\ContentSource;
use App\Models\QuestionImportBatch;
use App\Services\BulkQuestionImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class RetryFailedQuestionImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_failed_import_can_be_retried_and_cleared_from_review_queue(): void
    {
        $source = ContentSource::create([
            'name' => 'Retry Test Source',
            'slug' => 'retry-test-source-'.uniqid(),
            'source_type' => 'official',
            'base_url' => 'https://example.test',
            'trust_score' => 100,
            'allow_current_affairs' => true,
            'allow_question_generation' => true,
            'auto_publish_allowed' => false,
            'license_note' => 'Official public examination material.',
            'usage_notes' => 'Retry test source.',
            'is_active' => true,
        ]);

        $path = base_path('tests/Fixtures/two-question-chunks.json');

        $batch = QuestionImportBatch::create([
            'content_source_id' => $source->id,
            'batch_code' => 'QB-FAILED-RETRY',
            'batch_type' => 'json',
            'received_count' => 1,
            'accepted_count' => 0,
            'duplicate_count' => 0,
            'rejected_count' => 1,
            'status' => 'failed',
            'error_message' => 'Source approval was temporarily lost.',
            'metadata' => ['file' => $path],
            'started_at' => now()->subMinute(),
            'completed_at' => now(),
        ]);

        $service = Mockery::mock(BulkQuestionImportService::class);
        $service->shouldReceive('importJsonFile')
            ->once()
            ->with($path, Mockery::on(fn (ContentSource $value): bool => $value->is($source)), 250)
            ->andReturn([
                'received' => 2,
                'accepted' => 2,
                'duplicates' => 0,
                'rejected' => 0,
            ]);
        $this->app->instance(BulkQuestionImportService::class, $service);

        $this->artisan('question-bank:retry-import QB-FAILED-RETRY --chunk=250')
            ->expectsOutput('Question import retry completed.')
            ->assertSuccessful();

        $batch->refresh();

        $this->assertSame('completed', $batch->status);
        $this->assertSame(2, $batch->received_count);
        $this->assertSame(2, $batch->accepted_count);
        $this->assertSame(0, $batch->rejected_count);
        $this->assertNull($batch->error_message);
        $this->assertSame('completed', $batch->metadata['last_retry_status']);
        $this->assertSame(250, $batch->metadata['retry_chunk_size']);
    }

    public function test_completed_import_cannot_be_retried(): void
    {
        QuestionImportBatch::create([
            'batch_code' => 'QB-COMPLETED',
            'batch_type' => 'json',
            'status' => 'completed',
        ]);

        $this->artisan('question-bank:retry-import QB-COMPLETED')
            ->expectsOutput('Only failed question import batches can be retried.')
            ->assertFailed();
    }
}
