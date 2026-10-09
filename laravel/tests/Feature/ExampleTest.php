<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * A basic test example.
     */
    public function test_the_homepage_redirects_to_the_administrative_panel(): void
    {
        $response = $this->get('/');

        $response->assertRedirect('/admin');
    }

    public function test_the_admin_panel_requires_a_login(): void
    {
        $this->get('/admin')->assertRedirect('/login');
        $this->get('/login')->assertOk()->assertSee('Acceso administrativo');
    }
}
