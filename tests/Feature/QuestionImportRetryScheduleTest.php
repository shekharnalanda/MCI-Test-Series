<?php

namespace Tests\Feature;

use Tests\TestCase;

class QuestionImportRetryScheduleTest extends TestCase
{
    public function test_failed_import_retry_queue_is_scheduled(): void
    {
        $this->artisan('schedule:list')
            ->expectsOutputToContain('question-bank:retry-imports')
            ->assertSuccessful();
    }
}