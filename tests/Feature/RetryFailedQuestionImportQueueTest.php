<?php

namespace Tests\Feature;

use App\Models\ContentSource;
use App\Models\QuestionImportBatch;
use App\Services\BulkQuestionImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class RetryFailedQuestionImportQueueTest extends TestCase
{
    use RefreshDatabase;

    public function test_queue_retries_eligible_batches_and_skips_unavailable_files(): void
    {
        $source = ContentSource::create([
            'name' => 'Queue Retry Source',
            'slug' => 'queue-retry-source-'.uniqid(),
            'source_type' => 'official',
            'base_url' => 'https://example.test',
            'trust_score' => 100,
            'allow_current_affairs' => true,
            'allow_question_generation' => true,
            'auto_publish_allowed' => false,
            'license_note' => 'Official public examination material.',
            'usage_notes' => 'Queue retry test.',
            'is_active' => true,
        ]);

        $path = base_path('tests/Fixtures/two-question-chunks.json');

        $eligible = $this->failedBatch($source, 'QB-QUEUE-READY', $path);
        $skipped = $this->failedBatch($source, 'QB-QUEUE-MISSING', $path.'.missing');

        $service = Mockery::mock(BulkQuestionImportService::class);
        $service->shouldReceive('importJsonFile')
            ->once()
            ->with($path, Mockery::on(fn (ContentSource $value): bool => $value->is($source)), 250)
            ->andReturn([
                'received' => 2,
                'accepted' => 2,
                'duplicates' => 0,
                'rejected' => 0,
                'batches' => 1,
            ]);
        $this->app->instance(BulkQuestionImportService::class, $service);

        $this->artisan('question-bank:retry-imports --limit=10 --chunk=250')
            ->expectsOutput('Retry queue: selected=2 completed=1 skipped=1 failed=0')
            ->assertSuccessful();

        $this->assertSame('completed', $eligible->fresh()->status);
        $this->assertSame('failed', $skipped->fresh()->status);
    }

    public function test_strict_mode_fails_when_a_batch_is_skipped(): void
    {
        $source = ContentSource::create([
            'name' => 'Strict Queue Source',
            'slug' => 'strict-queue-source-'.uniqid(),
            'source_type' => 'official',
            'base_url' => 'https://example.test',
            'trust_score' => 100,
            'allow_question_generation' => true,
            'is_active' => true,
        ]);

        $this->failedBatch($source, 'QB-QUEUE-STRICT', '/missing/questions.json');

        $this->artisan('question-bank:retry-imports --strict')
            ->expectsOutput('Retry queue: selected=1 completed=0 skipped=1 failed=0')
            ->assertFailed();
    }

    private function failedBatch(ContentSource $source, string $code, string $path): QuestionImportBatch
    {
        return QuestionImportBatch::create([
            'content_source_id' => $source->id,
            'batch_code' => $code,
            'batch_type' => 'json',
            'received_count' => 1,
            'rejected_count' => 1,
            'status' => 'failed',
            'error_message' => 'Import interrupted.',
            'metadata' => ['file' => $path],
            'started_at' => now()->subMinute(),
            'completed_at' => now(),
        ]);
    }
}
