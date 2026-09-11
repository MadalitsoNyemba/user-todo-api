<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_authenticated_user_can_read_their_profile(): void
    {
        [$user, $token] = $this->userWithToken('alice@example.com');

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/me');

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.id', $user->id);
        $response->assertJsonPath('data.email', 'alice@example.com');
        $response->assertJsonStructure(['data' => ['id', 'name', 'email', 'role', 'created_at']]);
        $response->assertJsonMissingPath('data.password');
    }

    public function test_the_authenticated_user_can_rename_without_current_password(): void
    {
        [, $token] = $this->userWithToken('alice@example.com');

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->patchJson('/api/v1/me', [
                'name' => 'Alice Renamed',
                'email' => 'alice@example.com',
            ]);

        $response->assertOk();
        $response->assertJsonPath('data.name', 'Alice Renamed');
        $response->assertJsonPath('data.email', 'alice@example.com');

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/me')
            ->assertOk();
    }

    public function test_updating_email_or_password_requires_current_password(): void
    {
        [, $token] = $this->userWithToken('alice@example.com');

        $emailChange = $this->withHeader('Authorization', "Bearer {$token}")
            ->patchJson('/api/v1/me', [
                'name' => 'Alice Example',
                'email' => 'alice.updated@example.com',
            ]);

        $emailChange->assertStatus(422);
        $emailChange->assertJsonStructure(['errors' => ['current_password']]);

        $passwordChange = $this->withHeader('Authorization', "Bearer {$token}")
            ->patchJson('/api/v1/me', [
                'name' => 'Alice Example',
                'email' => 'alice@example.com',
                'password' => 'new-correct-horse',
                'password_confirmation' => 'new-correct-horse',
            ]);

        $passwordChange->assertStatus(422);
        $passwordChange->assertJsonStructure(['errors' => ['current_password']]);
    }

    public function test_wrong_current_password_is_rejected(): void
    {
        [, $token] = $this->userWithToken('alice@example.com');

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->patchJson('/api/v1/me', [
                'name' => 'Alice Example',
                'email' => 'alice.updated@example.com',
                'current_password' => 'not-the-password',
            ]);

        $response->assertStatus(422);
        $response->assertJsonStructure(['errors' => ['current_password']]);
    }

    public function test_changing_password_revokes_outstanding_tokens(): void
    {
        [$user, $token] = $this->userWithToken('alice@example.com');
        $otherSession = JWTAuth::fromUser($user);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->patchJson('/api/v1/me', [
                'name' => 'Alice Example',
                'email' => 'alice@example.com',
                'current_password' => 'correct-horse-battery',
                'password' => 'new-correct-horse',
                'password_confirmation' => 'new-correct-horse',
            ]);

        $response->assertOk();
        $response->assertJsonPath('message', 'Profile updated.');

        $this->assertTrue(Hash::check('new-correct-horse', $user->fresh()->password));
        $this->assertSame(1, $user->fresh()->token_version);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/me')
            ->assertStatus(401);

        $this->withHeader('Authorization', "Bearer {$otherSession}")
            ->getJson('/api/v1/me')
            ->assertStatus(401);

        $this->postJson('/api/v1/auth/login', [
            'email' => 'alice@example.com',
            'password' => 'new-correct-horse',
        ])->assertOk();
    }

    public function test_changing_email_with_current_password_revokes_the_token(): void
    {
        [, $token] = $this->userWithToken('alice@example.com');

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->patchJson('/api/v1/me', [
                'name' => 'Alice Updated',
                'email' => 'alice.updated@example.com',
                'current_password' => 'correct-horse-battery',
            ]);

        $response->assertOk();
        $response->assertJsonPath('data.email', 'alice.updated@example.com');

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/me')
            ->assertStatus(401);

        $this->postJson('/api/v1/auth/login', [
            'email' => 'alice.updated@example.com',
            'password' => 'correct-horse-battery',
        ])->assertOk();
    }

    public function test_updating_to_another_users_email_is_rejected(): void
    {
        User::factory()->create(['email' => 'bob@example.com']);
        [, $token] = $this->userWithToken('alice@example.com');

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->patchJson('/api/v1/me', [
                'name' => 'Alice Example',
                'email' => 'bob@example.com',
                'current_password' => 'correct-horse-battery',
            ]);

        $response->assertStatus(422);
        $response->assertJsonPath('success', false);
        $response->assertJsonStructure(['errors' => ['email']]);
    }

    public function test_deleting_the_account_removes_the_user_and_invalidates_the_token(): void
    {
        [$user, $token] = $this->userWithToken('alice@example.com');

        $delete = $this->withHeader('Authorization', "Bearer {$token}")
            ->deleteJson('/api/v1/me');

        $delete->assertOk();
        $delete->assertJsonPath('success', true);
        $delete->assertJsonPath('message', 'Account deleted.');
        $delete->assertJsonPath('data', null);

        $this->assertDatabaseMissing('users', ['id' => $user->id]);

        $reuse = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/me');

        $reuse->assertStatus(401);
        $reuse->assertJsonPath('success', false);
    }

    /**
     * @dataProvider unauthenticatedProfileMethods
     */
    public function test_profile_routes_reject_unauthenticated_requests(string $method): void
    {
        $response = $this->json($method, '/api/v1/me', [
            'name' => 'Nobody',
            'email' => 'nobody@example.com',
        ]);

        $response->assertStatus(401);
        $response->assertJsonPath('success', false);
    }

    public static function unauthenticatedProfileMethods(): array
    {
        return [
            'GET' => ['GET'],
            'PATCH' => ['PATCH'],
            'DELETE' => ['DELETE'],
        ];
    }

    /**
     * @return array{0: User, 1: string}
     */
    private function userWithToken(string $email): array
    {
        $user = User::factory()->create([
            'email' => $email,
            'password' => 'correct-horse-battery',
        ]);

        $token = $this->postJson('/api/v1/auth/login', [
            'email' => $email,
            'password' => 'correct-horse-battery',
        ]);

        $token->assertOk();
        $bearer = $token->json('data.token');

        $this->assertIsString($bearer);
        $this->assertNotEmpty($bearer);

        return [$user, $bearer];
    }
}
