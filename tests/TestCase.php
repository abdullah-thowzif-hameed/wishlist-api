<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Routing\Middleware\ThrottleRequests;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Every test request resolves to the same IP, so the shared "auth" rate
        // limit bucket would otherwise accumulate across unrelated test methods
        // within a single suite run and cause unrelated tests to see a 429.
        // Rate limiting itself is covered separately, not by these tests.
        $this->withoutMiddleware(ThrottleRequests::class);
    }
}
