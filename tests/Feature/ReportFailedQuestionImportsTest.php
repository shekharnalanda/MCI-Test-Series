<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ReportFailedQuestionImportsTest extends TestCase
{
    use RefreshDatabase;

    public function test_failed_batches_are_listed_and_fail_in_strict_mode(): void
    {
        DB::table('question_import_batches')->insert([
            'batch_code' => 'QB-FAILED-REVIEW',
            'batch_type' => 'json',
            'received_count' => 10,
            'accepted_count' => 5,
            'duplicate_count' => 1,
            'rejected_count' => 4,
            'status' => 'failed',
            'error_message' => 'Source quarantined during import.',
            'started_at' => now()->subMinute(),
            'completed_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->artisan('question-bank:failed-imports --strict')
            ->expectsOutput('Failed question import batches: 1')
            ->assertFailed();
    }

    public function test_empty_failed_import_queue_is_successful(): void
    {
        $this->artisan('question-bank:failed-imports --strict')
            ->expectsOutput('No failed question import batches found.')
            ->assertSuccessful();
    }
}
