<?php

namespace App\Http\Middleware;

use Illuminate\Auth\Middleware\Authenticate as Middleware;
use Illuminate\Http\Request;

class Authenticate extends Middleware
{
    /**
     * This is a JSON-only API, so unauthenticated requests should always
     * result in a 401 response rather than a redirect to a "login" route.
     */
    protected function redirectTo(Request $request): ?string
    {
        return null;
    }
}
