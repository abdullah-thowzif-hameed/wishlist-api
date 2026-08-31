<?php

namespace Database\Seeders;

use App\Models\Product;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ProductSeeder extends Seeder
{
    /**
     * Named products with deliberately overlapping keywords (e.g. "Wireless",
     * "Leather", "Running Shoes") so search behavior can be exercised, plus a
     * couple of inactive ones to test the "no longer available" case.
     */
    private const PRODUCTS = [
        ['name' => 'Wireless Mouse', 'price' => 24.99, 'description' => 'A responsive wireless mouse with a 6-month battery life.'],
        ['name' => 'Wireless Keyboard', 'price' => 39.99, 'description' => 'Slim wireless keyboard with quiet scissor-switch keys.'],
        ['name' => 'Wireless Noise-Cancelling Headphones', 'price' => 149.99, 'description' => 'Over-ear headphones with active noise cancellation.'],
        ['name' => 'Bluetooth Portable Speaker', 'price' => 59.99, 'description' => 'Compact speaker with 12 hours of playback.'],
        ['name' => '4K Ultra HD Monitor', 'price' => 329.00, 'description' => '27-inch 4K monitor with HDR support.'],
        ['name' => 'Mechanical Gaming Keyboard', 'price' => 89.99, 'description' => 'RGB backlit keyboard with hot-swappable switches.'],
        ['name' => 'Leather Laptop Bag', 'price' => 74.50, 'description' => 'Full-grain leather bag that fits up to a 15-inch laptop.'],
        ['name' => 'Vintage Leather Wallet', 'price' => 34.00, 'description' => 'Slim bifold wallet made from vintage-finish leather.'],
        ['name' => 'Stainless Steel Water Bottle', 'price' => 19.99, 'description' => 'Insulated bottle that keeps drinks cold for 24 hours.'],
        ['name' => 'Ceramic Coffee Mug Set', 'price' => 22.00, 'description' => 'Set of 4 handmade ceramic mugs.'],
        ['name' => 'Non-Stick Frying Pan', 'price' => 27.99, 'description' => '10-inch non-stick frying pan, oven safe up to 400°F.'],
        ['name' => 'Yoga Mat Pro', 'price' => 32.00, 'description' => 'Extra-thick non-slip yoga mat with carry strap.'],
        ['name' => 'Adjustable Dumbbell Set', 'price' => 129.99, 'description' => 'Pair of dumbbells adjustable from 5 to 25 lbs each.'],
        ['name' => "Running Shoes - Men's", 'price' => 79.99, 'description' => 'Lightweight running shoes with breathable mesh upper.'],
        ['name' => "Running Shoes - Women's", 'price' => 79.99, 'description' => 'Lightweight running shoes with cushioned sole.'],
        ['name' => 'Organic Cotton T-Shirt', 'price' => 18.00, 'description' => 'Soft, breathable t-shirt made from 100% organic cotton.'],
        ['name' => 'Denim Jacket', 'price' => 64.99, 'description' => 'Classic fit denim jacket with button closure.'],
        ['name' => 'Classic Aviator Sunglasses', 'price' => 45.00, 'description' => 'UV400-protected aviator sunglasses with metal frame.'],
        ['name' => 'Discontinued Vintage Radio', 'price' => 55.00, 'description' => 'A retro-style radio that has been discontinued.', 'status' => 'inactive'],
        ['name' => 'Retired Model Smartwatch', 'price' => 99.00, 'description' => 'Last-generation smartwatch, no longer sold.', 'status' => 'inactive'],
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach (self::PRODUCTS as $product) {
            Product::updateOrCreate(
                ['slug' => Str::slug($product['name'])],
                [
                    'name' => $product['name'],
                    'description' => $product['description'],
                    'price' => $product['price'],
                    'currency' => 'USD',
                    'status' => $product['status'] ?? 'active',
                ]
            );
        }

        // Pad out the catalog so listing endpoints have enough rows to
        // paginate across multiple pages.
        Product::factory()->count(20)->create();
    }
}
