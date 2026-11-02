<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * A basic test example.
     */
    public function test_the_application_returns_a_successful_response(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
    }

    public function test_the_version_endpoint_returns_the_package_version(): void
    {
        $response = $this->getJson('/api/v1/version');

        $response->assertOk()
            ->assertExactJson([
                'version' => config('app.version'),
            ]);
    }
}
