<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_login_with_correct_credentials_and_use_the_token(): void
    {
        User::factory()->create([
            'email' => 'jane@example.com',
            'password' => Hash::make('correct-password'),
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'jane@example.com',
            'password' => 'correct-password',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user.email', 'jane@example.com');

        $token = $response->json('data.token');
        $this->assertNotEmpty($token);

        // Prove the token actually authenticates on a protected route.
        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/me')
            ->assertStatus(200)
            ->assertJsonPath('data.user.email', 'jane@example.com');
    }

    public function test_login_fails_with_wrong_password(): void
    {
        User::factory()->create([
            'email' => 'jane@example.com',
            'password' => Hash::make('correct-password'),
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'jane@example.com',
            'password' => 'wrong-password',
        ]);

        $response->assertStatus(401)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Invalid credentials.');
    }

    public function test_login_fails_for_an_email_that_does_not_exist(): void
    {
        $response = $this->postJson('/api/login', [
            'email' => 'nobody@example.com',
            'password' => 'whatever123',
        ]);

        $response->assertStatus(401)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Invalid credentials.');
    }

    public function test_login_error_message_does_not_reveal_whether_the_email_exists(): void
    {
        User::factory()->create([
            'email' => 'jane@example.com',
            'password' => Hash::make('correct-password'),
        ]);

        $wrongPassword = $this->postJson('/api/login', [
            'email' => 'jane@example.com',
            'password' => 'wrong-password',
        ]);

        $unknownEmail = $this->postJson('/api/login', [
            'email' => 'nobody@example.com',
            'password' => 'wrong-password',
        ]);

        $this->assertSame($wrongPassword->json('message'), $unknownEmail->json('message'));
        $this->assertSame($wrongPassword->getStatusCode(), $unknownEmail->getStatusCode());
    }

    public function test_login_requires_email_and_password(): void
    {
        $response = $this->postJson('/api/login', []);

        $response->assertStatus(422)->assertJsonValidationErrors(['email', 'password']);
    }
}
