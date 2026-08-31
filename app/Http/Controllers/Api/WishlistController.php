<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Wishlist\StoreWishlistRequest;
use App\Http\Resources\WishlistResource;
use App\Models\Product;
use App\Models\Wishlist;
use App\Traits\ApiResponse;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WishlistController extends Controller
{
    use ApiResponse;

    /**
     * List the authenticated user's wishlist. Eager-loads the product on
     * each entry in one query to avoid an N+1.
     */
    public function index(Request $request): JsonResponse
    {
        $wishlists = $request->user()->wishlists()->with('product')->latest()->get();

        $wishlists->each(function (Wishlist $wishlist) {
            $wishlist->product->is_wishlisted = true;
        });

        return $this->success([
            'wishlist' => WishlistResource::collection($wishlists),
        ], 'Wishlist retrieved successfully.');
    }

    /**
     * Add a product to the authenticated user's wishlist. The owner is
     * always the authenticated user — there is no user_id input, so a
     * caller cannot target anyone else's wishlist.
     */
    public function store(StoreWishlistRequest $request): JsonResponse
    {
        $user = $request->user();
        $product = Product::findOrFail((int) $request->validated('product_id'));

        if ($user->wishlists()->where('product_id', $product->id)->exists()) {
            return $this->error('Product is already in your wishlist.', 409);
        }

        try {
            $wishlist = $user->wishlists()->create(['product_id' => $product->id]);
        } catch (QueryException) {
            // Unique constraint caught a race: another request added the same
            // product between our check above and this insert.
            return $this->error('Product is already in your wishlist.', 409);
        }

        $wishlist->setRelation('product', $product);
        $product->is_wishlisted = true;

        return $this->success([
            'wishlist_item' => new WishlistResource($wishlist),
        ], 'Product added to wishlist.', 201);
    }

    /**
     * Remove a single product from the authenticated user's wishlist. The
     * delete query is scoped to the authenticated user's own wishlist rows,
     * so a caller cannot remove another user's entry even by guessing IDs.
     */
    public function destroy(Product $product, Request $request): JsonResponse
    {
        $deleted = $request->user()->wishlists()->where('product_id', $product->id)->delete();

        if ($deleted === 0) {
            return $this->error('Product is not in your wishlist.', 404);
        }

        return $this->success(null, 'Product removed from wishlist.');
    }

    /**
     * Remove every product from the authenticated user's wishlist.
     */
    public function clear(Request $request): JsonResponse
    {
        $removed = $request->user()->wishlists()->delete();

        return $this->success([
            'removed_count' => $removed,
        ], 'Wishlist cleared.');
    }
}
