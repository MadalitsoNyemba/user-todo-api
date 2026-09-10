<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RateLimitingTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_sixth_login_attempt_within_a_minute_returns_429(): void
    {
        User::factory()->create([
            'email' => 'alice@example.com',
            'password' => 'correct-horse-battery',
        ]);

        $payload = [
            'email' => 'alice@example.com',
            'password' => 'wrong-password',
        ];

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->postJson('/api/v1/auth/login', $payload)
                ->assertStatus(401)
                ->assertJsonPath('success', false);
        }

        $response = $this->postJson('/api/v1/auth/login', $payload);

        $response->assertStatus(429);
        $response->assertJsonPath('success', false);
        $response->assertJsonPath('message', 'Too many requests. Please slow down.');
        $response->assertHeader('Retry-After');
    }
}
