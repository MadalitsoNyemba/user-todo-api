<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Models\User;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

class JwtGuardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // A protected route to authenticate against. The endpoints that will
        // really sit here arrive in the auth slice; this only needs to prove
        // the guard and the middleware behave.
        Route::middleware(['api', 'auth.jwt'])->get('/api/v1/_jwt-probe', function () {
            return response()->json(['id' => auth()->id()]);
        });
    }

    public function test_the_default_guard_uses_the_jwt_driver(): void
    {
        $this->assertSame('api', config('auth.defaults.guard'));
        $this->assertSame('jwt', config('auth.guards.api.driver'));
    }

    public function test_a_valid_token_resolves_the_authenticated_user(): void
    {
        $user = User::factory()->create();
        $token = JWTAuth::fromUser($user);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->get('/api/v1/_jwt-probe');

        $response->assertOk();
        $response->assertJsonPath('id', $user->id);
    }

    public function test_a_missing_token_is_rejected(): void
    {
        $response = $this->get('/api/v1/_jwt-probe');

        $response->assertStatus(401);
        $response->assertHeader('content-type', 'application/json');
        $response->assertJsonPath('success', false);
    }

    /**
     * The reason AuthenticateWithJwt exists. Through the stock auth:api
     * middleware the guard swallows both cases and they are indistinguishable.
     */
    public function test_an_invalid_token_says_so(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer not-a-real-token')
            ->get('/api/v1/_jwt-probe');

        $response->assertStatus(401);
        $response->assertJsonPath('message', 'Token is invalid.');
    }

    public function test_an_expired_token_is_distinguishable_from_an_invalid_one(): void
    {
        $user = User::factory()->create();

        // Issue the token as though the clock were two hours ago, so it is
        // already past the 60 minute TTL by the time it is presented.
        $this->travel(-2)->hours();
        $token = JWTAuth::fromUser($user);
        $this->travelBack();

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->get('/api/v1/_jwt-probe');

        $response->assertStatus(401);
        $response->assertJsonPath('message', 'Token has expired. Refresh it or log in again.');
    }

    /**
     * authenticate() returns false when the token's subject id no longer
     * resolves to a user. The middleware must reject the request itself
     * rather than let it through with no user set on the guard.
     */
    public function test_a_token_for_a_deleted_user_is_rejected(): void
    {
        $user = User::factory()->create();
        $token = JWTAuth::fromUser($user);
        $user->delete();

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->get('/api/v1/_jwt-probe');

        $response->assertStatus(401);
        $response->assertJsonPath('message', 'Unauthenticated.');
    }

    public function test_the_seeder_creates_demo_users_and_is_repeatable(): void
    {
        $this->seed(UserSeeder::class);
        $this->seed(UserSeeder::class);

        $this->assertSame(3, User::whereIn('email', [
            'alice@example.com',
            'bob@example.com',
            'admin@example.com',
        ])->count());

        $this->assertTrue(
            auth()->validate(['email' => 'alice@example.com', 'password' => 'password'])
        );
        $this->assertTrue(
            auth()->validate(['email' => 'admin@example.com', 'password' => 'password'])
        );
    }
}
