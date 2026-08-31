# Wishlist API

A Laravel 12 back-end REST API for an e-commerce "Wishlist" feature. Users register, log in with a Sanctum token, browse/search the product catalog, and manage a personal wishlist (add, view, remove, clear).

This is an **API-only** application — there is no front end or Blade UI.

## Tech stack

- **PHP 8.2+** / **Laravel 12**
- **Laravel Sanctum** — token-based authentication (`Authorization: Bearer <token>`)
- **SQLite** — local/dev database, zero external services required
- **PHPUnit** — feature test suite

## Requirements

- PHP >= 8.2 with the `pdo_sqlite`, `mbstring`, `tokenizer`, `xml`, `ctype`, `bcmath`, `fileinfo`, and `openssl` extensions (all standard/enabled by default in most PHP distributions, including XAMPP's bundled PHP)
- Composer 2.x

## Setup

```bash
git clone <repo-url> wishlist-assessment
cd wishlist-assessment

composer install

cp .env.example .env
php artisan key:generate
```

The `.env.example` already defaults to SQLite (`DB_CONNECTION=sqlite`), so no database server setup is needed. Create the empty database file:

```bash
# macOS / Linux
touch database/database.sqlite

# Windows (PowerShell)
New-Item -ItemType File -Path database/database.sqlite -Force
```

Run migrations and seed the product catalog:

```bash
php artisan migrate --seed
```

This creates all tables and seeds **40 products** (38 active, 2 marked `inactive`/delisted, with deliberately overlapping keywords so search has something to match) plus one convenience user:

| email | password |
|---|---|
| `test@example.com` | `password` |

Start the server:

```bash
php artisan serve
```

The API is now available at `http://127.0.0.1:8000/api`.

### Resetting the database

To wipe and reseed at any point:

```bash
php artisan migrate:fresh --seed
```

## Running tests

```bash
php artisan test
```

The suite (45 feature tests) runs against an **in-memory SQLite database** (configured in `phpunit.xml`), so it never touches your local `database/database.sqlite` — safe to run at any time without needing to reseed afterward.

Tests cover: registration, login (including that a wrong password and an unknown email return an identical response, so login can't be used to enumerate accounts), logout/token revocation, product browsing/search/sort/pagination, delisted-product visibility rules, wishlist add/remove/clear, duplicate prevention, and cross-user data isolation.

## Code quality

```bash
vendor/bin/pint             # formatter (Laravel Pint) — fixes in place
vendor/bin/pint --test      # check only, no changes
vendor/bin/phpstan analyse  # static analysis (Larastan), level 6
```

Both are dev dependencies, already in `composer.json`, and both run clean on the current codebase.

## Authentication

Auth is token-based via Sanctum. Register or log in to receive a token, then send it on every protected request:

```
Authorization: Bearer <token>
Accept: application/json
```

Product browsing (`GET /api/products*`) is public. Everything else under `/api/wishlist` and `/api/me` / `/api/logout` requires a valid token.

`/api/register` and `/api/login` share a rate limit of **5 requests/minute per IP** to slow down brute-forcing and registration spam; exceeding it returns `429`.

## Response format

Every response — success or error — uses the same envelope:

```jsonc
// success
{ "success": true, "message": "...", "data": { ... } }

// error
{ "success": false, "message": "...", "errors": { ... } | null }
```

`errors` is a field-keyed object for validation failures (`422`) and `null` for everything else. This is consistent across the whole API, including framework-level errors (404 on an unknown route, 405 on a wrong verb, 401 on missing/invalid auth, 429 on rate limiting, 500 on an unexpected error) — none of those fall through to Laravel's default HTML error pages.

## API reference

Base URL: `http://127.0.0.1:8000/api`

A ready-to-import [OpenAPI spec](docs/openapi.yaml) and [Postman collection](docs/postman_collection.json) are included — see [Interactive docs](#interactive-docs) below. What follows is the same reference in prose.

### Auth

#### `POST /register`

Create an account and receive a token immediately.

Request body:
```json
{ "name": "Ada Lovelace", "email": "ada@example.com", "password": "secret123" }
```
`password` requires a minimum of 8 characters. `email` must be unique.

`201 Created`:
```json
{
  "success": true,
  "message": "Registered successfully.",
  "data": {
    "user": { "id": 1, "name": "Ada Lovelace", "email": "ada@example.com", "created_at": "...", "updated_at": "..." },
    "token": "1|xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx"
  }
}
```

`422` if a field is missing/invalid or the email is already taken.

#### `POST /login`

Request body: `{ "email": "...", "password": "..." }`

`200 OK` — same shape as register's `data`.

`401 Unauthorized` on any failure — `{"message": "Invalid credentials."}`. This message and its status code are **identical** whether the email doesn't exist or the password is wrong, so a caller cannot use login to discover which emails are registered.

#### `GET /me` 🔒

Returns the authenticated user. `200` → `{ "data": { "user": {...} } }`.

#### `POST /logout` 🔒

Revokes the token used to make the request (other active tokens/sessions for the same user are unaffected). `200` → `{ "data": null }`.

### Products

#### `GET /products`

Public. Lists **active (available) products only**, with search, sort, and pagination.

Query parameters (all optional):

| Param | Notes |
|---|---|
| `search` | Matches against `name` **or** `description`, case-insensitive |
| `sort` | One of `name`, `-name`, `price`, `-price`, `created_at`, `-created_at` (leading `-` = descending). Default: `-created_at` |
| `per_page` | 1–50. Default: 15 |
| `page` | Standard page number |

Example: `GET /products?search=wireless&sort=price&per_page=10&page=2`

`200 OK`:
```json
{
  "success": true,
  "message": "Products retrieved successfully.",
  "data": {
    "products": [
      {
        "id": 1, "name": "Wireless Mouse", "slug": "wireless-mouse",
        "description": "A responsive wireless mouse with a 6-month battery life.",
        "price": "24.99", "currency": "USD", "status": "active",
        "is_wishlisted": false,
        "created_at": "...", "updated_at": "..."
      }
    ],
    "pagination": { "current_page": 1, "per_page": 15, "total": 38, "last_page": 3 }
  }
}
```

If the request carries a valid Sanctum token, each product's `is_wishlisted` reflects that user's own wishlist (computed with a single extra query for the whole page — not one query per product). Without a token, `is_wishlisted` is always `false`.

`422` if `sort` isn't one of the allowed values, or `per_page`/`page` is out of range.

#### `GET /products/{id}`

Public. Returns one product **regardless of status** — a delisted (`inactive`) product is intentionally still reachable by direct id, even though it's excluded from the listing/search above.

`200 OK` → `{ "data": { "product": {...} } }` (same shape as an index item).

`404 Not Found` if the id doesn't exist — `{"message": "Resource not found."}`.

### Wishlist 🔒

All endpoints below require a valid token and operate **only on the authenticated user's own wishlist** — there is no `user_id` parameter anywhere in the wishlist API, so there is no way to target another user's list, even by supplying one in the request body (it's simply ignored).

#### `GET /wishlist`

`200 OK`:
```json
{
  "success": true,
  "message": "Wishlist retrieved successfully.",
  "data": {
    "wishlist": [
      { "id": 3, "product": { "...": "same shape as a product above" }, "added_at": "2026-08-31T08:00:00.000000Z" }
    ]
  }
}
```

#### `POST /wishlist`

Request body: `{ "product_id": 1 }`

`201 Created` → `{ "data": { "wishlist_item": {...} } }` (same shape as an entry above).

Failure cases:
| Status | Cause |
|---|---|
| `422` | `product_id` missing, non-numeric, or doesn't reference any product |
| `422` | Product exists but is **delisted** (`status: inactive`) — `"This product is no longer available and cannot be added to a wishlist."` |
| `409` | Product is **already** on this user's wishlist |

#### `DELETE /wishlist/{product}`

Removes one product from the caller's wishlist.

`200 OK` → `{ "message": "Product removed from wishlist.", "data": null }`

`404 Not Found` if that product isn't on *this user's* wishlist — `{"message": "Product is not in your wishlist."}` — including if it's actually on someone else's wishlist; the response never reveals that.

#### `DELETE /wishlist`

Clears the caller's entire wishlist.

`200 OK` → `{ "data": { "removed_count": 3 } }`. Safe to call on an empty wishlist (`removed_count: 0`).

## Interactive docs

- **OpenAPI 3.0**: [`docs/openapi.yaml`](docs/openapi.yaml) — paste into [editor.swagger.io](https://editor.swagger.io) or any OpenAPI viewer for an interactive reference.
- **Postman**: [`docs/postman_collection.json`](docs/postman_collection.json) — import directly into Postman. It ships with a `base_url` collection variable (defaults to `http://127.0.0.1:8000/api`) and auto-captures the token into a `token` variable whenever you run **Register** or **Login**, so every other request in the collection authenticates automatically.

## Notable design decisions

A few choices worth calling out for a reviewer, since they weren't spelled out explicitly in the brief:

- **Wishlist uniqueness is enforced at the database level** (a unique composite index on `(user_id, product_id)`), not just in application code — so it holds even under concurrent duplicate requests, not only sequential ones.
- **A delisted product remains individually viewable** (`GET /products/{id}`) but is excluded from the listing/search and **cannot** be newly added to a wishlist — it can, however, remain on a wishlist it was added to before being delisted.
- **Login is timing- and message-safe against account enumeration**: an unknown email and a correct-email-wrong-password both take a comparable amount of time (a dummy bcrypt comparison runs either way) and return the exact same `401` message.
- **Product browsing is public** (no login required) since gating an entire catalog behind auth isn't typical for e-commerce; only wishlist mutation and viewing are gated. Say the word if you'd rather have browsing require auth too.
