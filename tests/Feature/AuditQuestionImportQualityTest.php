<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AuditQuestionImportQualityTest extends TestCase
{
    use RefreshDatabase;

    public function test_recent_healthy_imports_pass_strict_audit(): void
    {
        $this->insertBatch('QB-QUALITY-OK', 10, 7, 2, 1);

        $this->artisan('question-bank:audit-import-quality --strict --max-rejection-rate=20 --max-duplicate-rate=30')
            ->expectsOutput('Question import quality is within configured thresholds.')
            ->assertSuccessful();
    }

    public function test_rejection_spike_fails_strict_audit(): void
    {
        $this->insertBatch('QB-QUALITY-BAD', 10, 4, 1, 5);

        $this->artisan('question-bank:audit-import-quality --strict --max-rejection-rate=20')
            ->expectsOutput('Question import quality thresholds exceeded.')
            ->assertFailed();
    }

    public function test_failed_batch_fails_strict_audit(): void
    {
        $this->insertBatch('QB-QUALITY-FAILED', 10, 5, 1, 4, 'failed');

        $this->artisan('question-bank:audit-import-quality --strict --max-rejection-rate=100 --max-duplicate-rate=100')
            ->expectsOutput('Question import quality thresholds exceeded.')
            ->assertFailed();
    }
    public function test_empty_window_is_successful(): void
    {
        $this->artisan('question-bank:audit-import-quality --strict')
            ->expectsOutput('No completed or failed question import batches found in the audit window.')
            ->assertSuccessful();
    }

    private function insertBatch(
        string $code,
        int $received,
        int $accepted,
        int $duplicates,
        int $rejected,
        string $status = 'completed'
    ): void {
        DB::table('question_import_batches')->insert([
            'batch_code' => $code,
            'batch_type' => 'manual',
            'received_count' => $received,
            'accepted_count' => $accepted,
            'duplicate_count' => $duplicates,
            'rejected_count' => $rejected,
            'status' => $status,
            'started_at' => now()->subMinute(),
            'completed_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
