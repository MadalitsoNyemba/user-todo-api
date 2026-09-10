<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Exceptions\DuplicateEmailException;
use App\Models\User;
use App\Services\AuthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Probe route so login-issued tokens can be checked against auth.jwt
        // before real protected endpoints exist.
        Route::middleware(['api', 'auth.jwt'])->get('/api/v1/_auth-probe', function () {
            return response()->json(['id' => auth()->id()]);
        });
    }

    public function test_registration_creates_a_user_and_returns_a_token(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Alice Example',
            'email' => 'alice@example.com',
            'password' => 'correct-horse-battery',
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.user.email', 'alice@example.com');
        $response->assertJsonPath('data.token_type', 'bearer');
        $response->assertJsonStructure(['data' => ['token', 'expires_in', 'user' => ['id', 'name', 'email']]]);

        $this->assertDatabaseHas('users', ['email' => 'alice@example.com']);
    }

    public function test_the_password_is_never_returned(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Alice Example',
            'email' => 'alice@example.com',
            'password' => 'correct-horse-battery',
        ]);

        $response->assertJsonMissingPath('data.user.password');
    }

    public function test_the_password_is_stored_hashed(): void
    {
        $this->postJson('/api/v1/auth/register', [
            'name' => 'Alice Example',
            'email' => 'alice@example.com',
            'password' => 'correct-horse-battery',
        ]);

        $user = User::where('email', 'alice@example.com')->firstOrFail();

        $this->assertNotSame('correct-horse-battery', $user->password);
        $this->assertTrue(Hash::check('correct-horse-battery', $user->password));
    }

    /**
     * @dataProvider invalidRegistrationPayloads
     */
    public function test_registration_rejects_invalid_input(array $payload, string $field): void
    {
        $response = $this->postJson('/api/v1/auth/register', $payload);

        $response->assertStatus(422);
        $response->assertJsonPath('success', false);
        $response->assertJsonStructure(['errors' => [$field]]);
    }

    public static function invalidRegistrationPayloads(): array
    {
        $valid = [
            'name' => 'Alice Example',
            'email' => 'alice@example.com',
            'password' => 'correct-horse-battery',
        ];

        return [
            'no name' => [array_merge($valid, ['name' => '']), 'name'],
            'no email' => [array_merge($valid, ['email' => '']), 'email'],
            'malformed email' => [array_merge($valid, ['email' => 'not-an-email']), 'email'],
            'short password' => [array_merge($valid, ['password' => 'short']), 'password'],
            // The CRLF mitigation recorded against CVE-2026-48019.
            'email with a carriage return' => [array_merge($valid, ['email' => "alice@example.com\r\nBcc: victim@example.com"]), 'email'],
        ];
    }

    public function test_registration_rejects_a_duplicate_email(): void
    {
        User::factory()->create(['email' => 'alice@example.com']);

        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Alice Again',
            'email' => 'alice@example.com',
            'password' => 'correct-horse-battery',
        ]);

        $response->assertStatus(422);
        $response->assertJsonStructure(['errors' => ['email']]);
        $this->assertSame(1, User::where('email', 'alice@example.com')->count());
    }

    public function test_login_returns_a_token(): void
    {
        User::factory()->create([
            'email' => 'alice@example.com',
            'password' => 'correct-horse-battery',
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'alice@example.com',
            'password' => 'correct-horse-battery',
        ]);

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.user.email', 'alice@example.com');
        $response->assertJsonStructure(['data' => ['token', 'token_type', 'expires_in']]);
    }

    public function test_login_with_the_wrong_password_is_rejected(): void
    {
        User::factory()->create([
            'email' => 'alice@example.com',
            'password' => 'correct-horse-battery',
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'alice@example.com',
            'password' => 'wrong',
        ]);

        $response->assertStatus(401);
        $response->assertJsonPath('success', false);
        $response->assertJsonPath('message', 'These credentials do not match our records.');
    }

    /**
     * An unknown address and a wrong password must be indistinguishable, or
     * the endpoint becomes a way to discover who has an account.
     */
    public function test_login_with_an_unknown_email_gives_the_same_answer(): void
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'nobody@example.com',
            'password' => 'whatever',
        ]);

        $response->assertStatus(401);
        $response->assertJsonPath('message', 'These credentials do not match our records.');
    }

    public function test_the_issued_token_works_against_a_protected_route(): void
    {
        $user = User::factory()->create([
            'email' => 'alice@example.com',
            'password' => 'correct-horse-battery',
        ]);

        $token = $this->postJson('/api/v1/auth/login', [
            'email' => 'alice@example.com',
            'password' => 'correct-horse-battery',
        ])->json('data.token');

        $this->assertIsString($token);
        $this->assertNotEmpty($token);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/_auth-probe');

        $response->assertOk();
        $response->assertJsonPath('id', $user->id);
    }

    /**
     * FormRequest uniqueness catches the common case. This exercises the race
     * where two requests pass validation and the second hits the DB unique index.
     */
    public function test_a_raced_duplicate_register_is_mapped_to_a_validation_error(): void
    {
        User::factory()->create(['email' => 'alice@example.com']);

        try {
            app(AuthService::class)->register(
                'Alice Again',
                'alice@example.com',
                'correct-horse-battery',
            );
            $this->fail('Expected DuplicateEmailException.');
        } catch (DuplicateEmailException $e) {
            $this->assertSame(422, $e->status());
            $this->assertSame(
                ['email' => ['The email has already been taken.']],
                $e->errors(),
            );
        }

        $this->assertSame(1, User::where('email', 'alice@example.com')->count());
    }
}
