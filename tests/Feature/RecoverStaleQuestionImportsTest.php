<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RecoverStaleQuestionImportsTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_reports_and_recovers_only_stale_processing_batches(): void
    {
        $now = now();

        DB::table('question_import_batches')->insert([
            [
                'batch_code' => 'QB-STALE-001',
                'batch_type' => 'manual',
                'received_count' => 1,
                'status' => 'processing',
                'started_at' => $now->copy()->subMinutes(90),
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'batch_code' => 'QB-ACTIVE-001',
                'batch_type' => 'manual',
                'received_count' => 1,
                'status' => 'processing',
                'started_at' => $now->copy()->subMinutes(5),
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);

        $this->artisan('question-bank:recover-imports --stale-minutes=30 --dry-run')
            ->expectsOutput('Dry run: 1 stale question import batch(es) found.')
            ->assertSuccessful();

        $this->assertDatabaseHas('question_import_batches', [
            'batch_code' => 'QB-STALE-001',
            'status' => 'processing',
        ]);

        $this->artisan('question-bank:recover-imports --stale-minutes=30')
            ->expectsOutput('Recovered 1 stale question import batch(es).')
            ->assertSuccessful();

        $this->assertDatabaseHas('question_import_batches', [
            'batch_code' => 'QB-STALE-001',
            'status' => 'failed',
        ]);

        $this->assertDatabaseHas('question_import_batches', [
            'batch_code' => 'QB-ACTIVE-001',
            'status' => 'processing',
        ]);
    }

    public function test_it_succeeds_when_there_are_no_stale_batches(): void
    {
        $this->artisan('question-bank:recover-imports')
            ->expectsOutput('No stale question import batches found.')
            ->assertSuccessful();
    }
}
