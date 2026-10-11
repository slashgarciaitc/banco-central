<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AdminAuthenticationTest extends TestCase
{
    public function test_only_allowlisted_supabase_users_can_login(): void
    {
        config([
            'central.supabase_url' => 'https://demo.supabase.co',
            'central.anon_key' => 'public-test-key',
            'central.admin_emails' => ['admin@example.test'],
        ]);
        Http::fake();

        $this->from('/login')->post('/login', [
            'email' => 'not-admin@example.test',
            'password' => 'password123',
        ])->assertRedirect('/login')->assertSessionHasErrors('email');

        Http::assertNothingSent();
    }

    public function test_an_allowlisted_supabase_user_can_open_the_admin_panel(): void
    {
        config([
            'central.supabase_url' => 'https://demo.supabase.co',
            'central.anon_key' => 'public-test-key',
            'central.service_role_key' => 'server-only-test-key',
            'central.admin_emails' => ['admin@example.test'],
        ]);
        Http::fake([
            'https://demo.supabase.co/auth/v1/token*' => Http::response([
                'access_token' => 'temporary-test-token',
                'user' => ['id' => 'user-123', 'email' => 'admin@example.test'],
            ]),
            'https://demo.supabase.co/rest/v1/nodos*' => Http::response([]),
            'https://demo.supabase.co/rest/v1/transactions*' => Http::response([]),
        ]);

        $this->post('/login', [
            'email' => 'admin@example.test',
            'password' => 'password123',
        ])->assertRedirect(route('admin.dashboard'));

        $this->get('/admin')->assertOk()->assertSee('Resumen del núcleo');
        $this->assertSame('admin@example.test', session('central_admin.email'));
    }
}
