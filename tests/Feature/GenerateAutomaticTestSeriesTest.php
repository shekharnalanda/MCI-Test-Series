<?php

namespace Tests\Feature;

use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class GenerateAutomaticTestSeriesTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_generates_tests_and_records_question_usage(): void
    {
        $this->seed(DatabaseSeeder::class);

        $before = DB::table('tests')->where('auto_generated', true)->count();

        $this->artisan('test-series:generate --questions=5 --per-exam=1')
            ->assertSuccessful();

        $after = DB::table('tests')->where('auto_generated', true)->count();

        $this->assertGreaterThan($before, $after);
        $this->assertGreaterThan(
            0,
            DB::table('questions')->where('usage_count', '>', 0)->count()
        );
    }
}
