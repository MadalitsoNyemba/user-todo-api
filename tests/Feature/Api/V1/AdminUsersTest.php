<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminUsersTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_admin_can_list_users(): void
    {
        User::factory()->create(['email' => 'alice@example.com']);
        [, $token] = $this->userWithToken('admin@example.com', UserRole::Admin);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/users');

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('meta.current_page', 1);
        $response->assertJsonStructure([
            'data' => [
                ['id', 'name', 'email', 'role', 'created_at'],
            ],
            'meta' => ['current_page', 'per_page', 'total', 'last_page'],
        ]);
        $this->assertGreaterThanOrEqual(2, $response->json('meta.total'));
    }

    public function test_a_non_admin_receives_403(): void
    {
        [, $token] = $this->userWithToken('alice@example.com', UserRole::User);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/users');

        $response->assertStatus(403);
        $response->assertJsonPath('success', false);
        $response->assertJsonPath('message', 'This action is unauthorized.');
    }

    public function test_unauthenticated_requests_receive_401(): void
    {
        $response = $this->getJson('/api/v1/users');

        $response->assertStatus(401);
        $response->assertJsonPath('success', false);
    }

    /**
     * @return array{0: User, 1: string}
     */
    private function userWithToken(string $email, UserRole $role): array
    {
        $user = User::factory()->create([
            'email' => $email,
            'password' => 'correct-horse-battery',
            'role' => $role,
        ]);

        $login = $this->postJson('/api/v1/auth/login', [
            'email' => $email,
            'password' => 'correct-horse-battery',
        ]);

        $login->assertOk();
        $token = $login->json('data.token');

        $this->assertIsString($token);
        $this->assertNotEmpty($token);

        return [$user, $token];
    }
}
