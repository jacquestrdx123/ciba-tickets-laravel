<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiTokenAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_api_returns_unauthorized_without_credentials(): void
    {
        config(['app.api_token' => 'secret-token']);

        $this->getJson('/api/tickets')
            ->assertUnauthorized()
            ->assertJsonPath('message', 'Unauthenticated.');
    }

    public function test_api_accepts_valid_bearer_token(): void
    {
        config(['app.api_token' => 'secret-token']);

        $this->withApiToken('secret-token')
            ->getJson('/api/tickets')
            ->assertOk()
            ->assertJsonStructure(['tickets']);
    }

    public function test_api_rejects_invalid_bearer_token(): void
    {
        config(['app.api_token' => 'secret-token']);

        $this->withHeader('Authorization', 'Bearer wrong-token')
            ->getJson('/api/tickets')
            ->assertUnauthorized();
    }

    public function test_api_accepts_session_auth_for_browser_clients(): void
    {
        config(['app.api_token' => 'secret-token']);

        $this->withSession(['authenticated' => true])
            ->getJson('/api/tickets')
            ->assertOk();
    }
}
