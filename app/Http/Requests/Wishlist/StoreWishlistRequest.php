<?php

namespace App\Http\Requests\Wishlist;

use App\Models\Product;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

class StoreWishlistRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Intentionally only accepts product_id. The owning user is always taken
     * from the authenticated request, never from client input, so there is
     * no way to add a product to someone else's wishlist.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'product_id' => ['required', 'integer', 'exists:products,id', $this->mustBeActive()],
        ];
    }

    /**
     * A delisted product still exists (exists:products,id passes), so it needs
     * its own check to produce a clear, distinct message rather than being
     * lumped in with "that id doesn't exist".
     */
    private function mustBeActive(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            $product = Product::find($value);

            if ($product && $product->status !== 'active') {
                $fail('This product is no longer available and cannot be added to a wishlist.');
            }
        };
    }
}
