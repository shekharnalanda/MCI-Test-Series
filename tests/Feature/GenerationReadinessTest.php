<?php

namespace Tests\Feature;

use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GenerationReadinessTest extends TestCase
{
    use RefreshDatabase;

    public function test_readiness_command_is_a_read_only_audit(): void
    {
        $this->seed(DatabaseSeeder::class);

        $before = $this->getConnection()->table('tests')->count();

        $this->artisan('test-series:readiness --questions=5 --min-pool-multiple=1')
            ->expectsOutputToContain('Read-only audit:')
            ->assertSuccessful();

        $this->assertSame(
            $before,
            $this->getConnection()->table('tests')->count()
        );
    }

    private function getConnection()
    {
        return app('db')->connection();
    }
}
