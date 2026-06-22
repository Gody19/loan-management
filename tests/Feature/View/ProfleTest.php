<?php

namespace Tests\Feature\View;

use Tests\TestCase;

class ProfleTest extends TestCase
{
    public function test_can_render(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
    }
}
