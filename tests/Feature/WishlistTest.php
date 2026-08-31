<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class WishlistTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_access_any_wishlist_endpoint(): void
    {
        $product = Product::factory()->create();

        $this->getJson('/api/wishlist')->assertStatus(401);
        $this->postJson('/api/wishlist', ['product_id' => $product->id])->assertStatus(401);
        $this->deleteJson("/api/wishlist/{$product->id}")->assertStatus(401);
        $this->deleteJson('/api/wishlist')->assertStatus(401);
    }

    public function test_authenticated_user_sees_an_empty_wishlist_by_default(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/wishlist')
            ->assertStatus(200)
            ->assertJsonPath('data.wishlist', []);
    }

    public function test_user_can_add_a_product_to_their_wishlist(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['name' => 'Wireless Mouse']);
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/wishlist', ['product_id' => $product->id]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.wishlist_item.product.name', 'Wireless Mouse');

        $this->assertDatabaseHas('wishlists', [
            'user_id' => $user->id,
            'product_id' => $product->id,
        ]);
    }

    public function test_added_product_appears_in_the_wishlist_view(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/wishlist', ['product_id' => $product->id]);

        $this->getJson('/api/wishlist')
            ->assertJsonCount(1, 'data.wishlist')
            ->assertJsonPath('data.wishlist.0.product.id', $product->id);
    }

    public function test_user_cannot_add_the_same_product_twice(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/wishlist', ['product_id' => $product->id])->assertStatus(201);
        $response = $this->postJson('/api/wishlist', ['product_id' => $product->id]);

        $response->assertStatus(409)->assertJsonPath('success', false);
        $this->assertSame(1, $user->wishlists()->where('product_id', $product->id)->count());
    }

    public function test_user_cannot_add_a_nonexistent_product(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/wishlist', ['product_id' => 999999])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['product_id']);
    }

    public function test_user_cannot_add_a_delisted_product(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->inactive()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/wishlist', ['product_id' => $product->id]);

        $response->assertStatus(422)->assertJsonValidationErrors(['product_id']);
        $this->assertDatabaseMissing('wishlists', [
            'user_id' => $user->id,
            'product_id' => $product->id,
        ]);
    }

    public function test_adding_a_product_requires_product_id(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/wishlist', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['product_id']);
    }

    public function test_user_can_remove_a_product_from_their_wishlist(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create();
        $user->wishlists()->create(['product_id' => $product->id]);
        Sanctum::actingAs($user);

        $this->deleteJson("/api/wishlist/{$product->id}")
            ->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertDatabaseMissing('wishlists', [
            'user_id' => $user->id,
            'product_id' => $product->id,
        ]);
    }

    public function test_removing_a_product_not_on_the_wishlist_returns_404(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create();
        Sanctum::actingAs($user);

        $this->deleteJson("/api/wishlist/{$product->id}")
            ->assertStatus(404)
            ->assertJsonPath('message', 'Product is not in your wishlist.');
    }

    public function test_removing_a_nonexistent_product_id_returns_404(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->deleteJson('/api/wishlist/999999')->assertStatus(404);
    }

    public function test_user_can_clear_their_entire_wishlist(): void
    {
        $user = User::factory()->create();
        $products = Product::factory()->count(3)->create();
        foreach ($products as $product) {
            $user->wishlists()->create(['product_id' => $product->id]);
        }
        Sanctum::actingAs($user);

        $response = $this->deleteJson('/api/wishlist');

        $response->assertStatus(200)->assertJsonPath('data.removed_count', 3);
        $this->assertSame(0, $user->wishlists()->count());
    }

    public function test_clearing_an_empty_wishlist_is_a_harmless_no_op(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->deleteJson('/api/wishlist')
            ->assertStatus(200)
            ->assertJsonPath('data.removed_count', 0);
    }

    // -- User data isolation ------------------------------------------------

    public function test_a_user_cannot_see_another_users_wishlist(): void
    {
        $owner = User::factory()->create();
        $product = Product::factory()->create();
        $owner->wishlists()->create(['product_id' => $product->id]);

        $intruder = User::factory()->create();
        Sanctum::actingAs($intruder);

        $this->getJson('/api/wishlist')->assertJsonPath('data.wishlist', []);
    }

    public function test_a_user_cannot_remove_another_users_wishlist_item(): void
    {
        $owner = User::factory()->create();
        $product = Product::factory()->create();
        $owner->wishlists()->create(['product_id' => $product->id]);

        $intruder = User::factory()->create();
        Sanctum::actingAs($intruder);

        $this->deleteJson("/api/wishlist/{$product->id}")->assertStatus(404);

        // The owner's entry must be completely untouched.
        $this->assertDatabaseHas('wishlists', [
            'user_id' => $owner->id,
            'product_id' => $product->id,
        ]);
    }

    public function test_a_user_cannot_clear_another_users_wishlist(): void
    {
        $owner = User::factory()->create();
        $product = Product::factory()->create();
        $owner->wishlists()->create(['product_id' => $product->id]);

        $intruder = User::factory()->create();
        Sanctum::actingAs($intruder);

        $this->deleteJson('/api/wishlist')->assertJsonPath('data.removed_count', 0);

        $this->assertDatabaseHas('wishlists', [
            'user_id' => $owner->id,
            'product_id' => $product->id,
        ]);
    }

    public function test_a_spoofed_user_id_in_the_request_body_is_ignored(): void
    {
        $attacker = User::factory()->create();
        $victim = User::factory()->create();
        $product = Product::factory()->create();
        Sanctum::actingAs($attacker);

        $this->postJson('/api/wishlist', [
            'product_id' => $product->id,
            'user_id' => $victim->id,
        ])->assertStatus(201);

        $this->assertDatabaseHas('wishlists', [
            'user_id' => $attacker->id,
            'product_id' => $product->id,
        ]);
        $this->assertDatabaseMissing('wishlists', [
            'user_id' => $victim->id,
            'product_id' => $product->id,
        ]);
    }
}
