<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlansPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_plans_can_be_compared_and_selected(): void
    {
        $this->get('/plans')
            ->assertOk()
            ->assertSee('₹199')
            ->assertSee('₹299')
            ->assertSee('₹499')
            ->assertSee('₹799')
            ->assertSee('MOST POPULAR')
            ->assertSee('BEST VALUE')
            ->assertSee('Read More Details')
            ->assertSee('Select Plan');

        $this->get('/admission?package=safalta-plan')
            ->assertOk()
            ->assertSee('Selected Plan:')
            ->assertSee('Safalta Plan');
    }
}
