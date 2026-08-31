<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProductTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_only_returns_active_products(): void
    {
        Product::factory()->count(3)->create();
        $delisted = Product::factory()->inactive()->create();

        $response = $this->getJson('/api/products');

        $response->assertStatus(200)->assertJsonPath('data.pagination.total', 3);

        $ids = collect($response->json('data.products'))->pluck('id');
        $this->assertNotContains($delisted->id, $ids);
    }

    public function test_show_works_for_a_delisted_product(): void
    {
        $product = Product::factory()->inactive()->create(['name' => 'Old Radio']);

        $this->getJson("/api/products/{$product->id}")
            ->assertStatus(200)
            ->assertJsonPath('data.product.name', 'Old Radio')
            ->assertJsonPath('data.product.status', 'inactive');
    }

    public function test_show_returns_404_for_a_missing_product(): void
    {
        $this->getJson('/api/products/999999')
            ->assertStatus(404)
            ->assertJsonPath('success', false);
    }

    public function test_index_can_search_by_name(): void
    {
        Product::factory()->create(['name' => 'Wireless Mouse']);
        Product::factory()->create(['name' => 'Wireless Keyboard']);
        Product::factory()->create(['name' => 'Denim Jacket']);

        $response = $this->getJson('/api/products?search=Wireless');

        $response->assertJsonPath('data.pagination.total', 2);
        $names = collect($response->json('data.products'))->pluck('name');
        $this->assertTrue($names->every(fn ($name) => str_contains($name, 'Wireless')));
    }

    public function test_index_can_search_by_description(): void
    {
        Product::factory()->create(['name' => 'Bottle', 'description' => 'Keeps drinks insulated for hours.']);
        Product::factory()->create(['name' => 'Mug', 'description' => 'A plain ceramic mug.']);

        $response = $this->getJson('/api/products?search=insulated');

        $response->assertJsonPath('data.pagination.total', 1)
            ->assertJsonPath('data.products.0.name', 'Bottle');
    }

    public function test_search_is_case_insensitive(): void
    {
        Product::factory()->create(['name' => 'Wireless Mouse']);

        $this->getJson('/api/products?search=WIRELESS')
            ->assertJsonPath('data.pagination.total', 1);
    }

    public function test_search_excludes_delisted_products(): void
    {
        Product::factory()->inactive()->create(['name' => 'Delisted Wireless Gadget']);

        $this->getJson('/api/products?search=Wireless')
            ->assertJsonPath('data.pagination.total', 0);
    }

    public function test_index_can_sort_by_price_ascending(): void
    {
        Product::factory()->create(['name' => 'Mid', 'price' => 50]);
        Product::factory()->create(['name' => 'Cheap', 'price' => 10]);
        Product::factory()->create(['name' => 'Expensive', 'price' => 100]);

        $response = $this->getJson('/api/products?sort=price');

        $this->assertSame(
            ['Cheap', 'Mid', 'Expensive'],
            collect($response->json('data.products'))->pluck('name')->all()
        );
    }

    public function test_index_can_sort_by_price_descending(): void
    {
        Product::factory()->create(['name' => 'Mid', 'price' => 50]);
        Product::factory()->create(['name' => 'Cheap', 'price' => 10]);
        Product::factory()->create(['name' => 'Expensive', 'price' => 100]);

        $response = $this->getJson('/api/products?sort=-price');

        $this->assertSame(
            ['Expensive', 'Mid', 'Cheap'],
            collect($response->json('data.products'))->pluck('name')->all()
        );
    }

    public function test_index_rejects_an_unsupported_sort_value(): void
    {
        $this->getJson('/api/products?sort=bogus')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['sort']);
    }

    public function test_index_paginates_results(): void
    {
        Product::factory()->count(20)->create();

        $firstPage = $this->getJson('/api/products?per_page=15');
        $firstPage->assertJsonPath('data.pagination.total', 20)
            ->assertJsonPath('data.pagination.last_page', 2)
            ->assertJsonCount(15, 'data.products');

        $secondPage = $this->getJson('/api/products?per_page=15&page=2');
        $secondPage->assertJsonCount(5, 'data.products');

        $firstPageIds = collect($firstPage->json('data.products'))->pluck('id');
        $secondPageIds = collect($secondPage->json('data.products'))->pluck('id');
        $this->assertEmpty($firstPageIds->intersect($secondPageIds));
    }

    public function test_index_rejects_a_per_page_value_above_the_maximum(): void
    {
        $this->getJson('/api/products?per_page=500')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['per_page']);
    }

    public function test_guest_sees_is_wishlisted_as_false(): void
    {
        Product::factory()->create();

        $this->getJson('/api/products')
            ->assertJsonPath('data.products.0.is_wishlisted', false);
    }

    public function test_authenticated_user_sees_their_own_wishlist_status(): void
    {
        $user = User::factory()->create();
        $wishlisted = Product::factory()->create(['name' => 'Wishlisted Item']);
        $other = Product::factory()->create(['name' => 'Other Item']);
        $user->wishlists()->create(['product_id' => $wishlisted->id]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/products');

        $byName = collect($response->json('data.products'))->keyBy('name');
        $this->assertTrue($byName['Wishlisted Item']['is_wishlisted']);
        $this->assertFalse($byName['Other Item']['is_wishlisted']);
    }
}
