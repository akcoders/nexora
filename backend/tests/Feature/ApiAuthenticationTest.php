<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_user_can_receive_an_api_token(): void
    {
        $user = User::factory()->create(['status' => 'active', 'password' => 'password']);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertOk()->assertJsonStructure(['token', 'user' => ['id', 'name', 'email']]);
        $this->assertDatabaseCount('personal_access_tokens', 1);
    }

    public function test_inactive_user_cannot_login_to_the_api(): void
    {
        $user = User::factory()->create(['status' => 'inactive', 'password' => 'password']);

        $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'password'])
            ->assertUnprocessable();
    }
}
