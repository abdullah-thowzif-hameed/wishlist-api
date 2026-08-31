<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class LogoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_logout_and_the_token_stops_working(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test-token')->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/logout')
            ->assertStatus(200)
            ->assertJsonPath('success', true);

        // Sanctum's guard memoizes the resolved user for the life of the test,
        // so without this a second call with the same token would read that
        // cached user instead of re-checking the (now token-less) database.
        Auth::forgetGuards();

        // The same token must no longer authenticate anything.
        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/me')
            ->assertStatus(401);
    }

    public function test_logging_out_only_revokes_the_token_that_was_used(): void
    {
        $user = User::factory()->create();
        $usedToken = $user->createToken('used')->plainTextToken;
        $otherToken = $user->createToken('other')->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$usedToken}")
            ->postJson('/api/logout')
            ->assertStatus(200);

        Auth::forgetGuards();

        $this->withHeader('Authorization', "Bearer {$otherToken}")
            ->getJson('/api/me')
            ->assertStatus(200);
    }

    public function test_logout_requires_authentication(): void
    {
        $this->postJson('/api/logout')->assertStatus(401);
    }
}
