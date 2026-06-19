<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function withApiToken(string $token = 'test-api-token'): static
    {
        config(['app.api_token' => $token]);

        return $this->withHeader('Authorization', 'Bearer '.$token);
    }
}
