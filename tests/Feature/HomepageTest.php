<?php

namespace Tests\Feature;

use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HomepageTest extends TestCase
{
    use RefreshDatabase;

    public function test_professional_homepage_displays_core_sections_and_contact_details(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->get('/')
            ->assertOk()
            ->assertSee('Prepare smarter')
            ->assertSee('Free demo tests')
            ->assertSee('One platform for major competitive exams')
            ->assertSee('7004773247')
            ->assertSee('9334779133')
            ->assertSee('mcieducationalgroup@gmail.com');
    }
}
