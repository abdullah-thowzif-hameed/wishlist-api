<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Product\IndexProductRequest;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use App\Models\User;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class ProductController extends Controller
{
    use ApiResponse;

    /**
     * List available products. Supports searching by name/description,
     * sorting, and pagination. Only active (available) products are
     * included here — a delisted product is only reachable via show().
     */
    public function index(IndexProductRequest $request): JsonResponse
    {
        $query = Product::query()->active();

        if ($search = $request->validated('search')) {
            $query->where(function ($inner) use ($search) {
                $inner->where('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        [$column, $direction] = $this->parseSort($request->validated('sort'));

        // `id` is a stable tiebreaker: several sortable columns (created_at in
        // particular, when rows are seeded in bulk) can share the same value,
        // which would otherwise make page ordering non-deterministic.
        $query->orderBy($column, $direction)->orderBy('id');

        $products = $query->paginate($request->validated('per_page') ?? 15);

        $this->annotateWishlistStatus($products->getCollection(), $request->user('sanctum'));

        return $this->success([
            'products' => ProductResource::collection($products->items()),
            'pagination' => [
                'current_page' => $products->currentPage(),
                'per_page' => $products->perPage(),
                'total' => $products->total(),
                'last_page' => $products->lastPage(),
            ],
        ], 'Products retrieved successfully.');
    }

    /**
     * Show a single product regardless of its status, so a direct link to a
     * delisted product still resolves.
     */
    public function show(Product $product, Request $request): JsonResponse
    {
        $user = $request->user('sanctum');

        $product->is_wishlisted = $user
            ? $user->wishlists()->where('product_id', $product->id)->exists()
            : false;

        return $this->success([
            'product' => new ProductResource($product),
        ], 'Product retrieved successfully.');
    }

    /**
     * @return array{0: string, 1: 'asc'|'desc'}
     */
    private function parseSort(?string $sort): array
    {
        $sort ??= '-created_at';

        $direction = str_starts_with($sort, '-') ? 'desc' : 'asc';
        $column = ltrim($sort, '-');

        return [$column, $direction];
    }

    /**
     * Set a transient `is_wishlisted` flag on each product using a single query
     * for the whole page, instead of one wishlist lookup per product (N+1).
     *
     * @param  Collection<int, Product>  $products
     */
    private function annotateWishlistStatus(Collection $products, ?User $user): void
    {
        $wishlistedIds = $user
            ? $user->wishlists()->whereIn('product_id', $products->pluck('id'))->pluck('product_id')->all()
            : [];

        $products->each(function (Product $product) use ($wishlistedIds) {
            $product->is_wishlisted = in_array($product->id, $wishlistedIds, true);
        });
    }
}
