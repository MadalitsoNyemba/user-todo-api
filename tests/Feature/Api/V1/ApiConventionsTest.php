<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ApiConventionsTest extends TestCase
{
    /**
     * No Accept header is set anywhere in this class on purpose. A bare curl
     * must never receive an HTML error page.
     */
    public function test_unknown_route_returns_the_envelope_as_json(): void
    {
        $response = $this->get('/api/v1/does-not-exist');

        $response->assertStatus(404);
        $response->assertHeader('content-type', 'application/json');
        $response->assertExactJson([
            'success' => false,
            'message' => 'Resource not found.',
            'data' => null,
            'errors' => null,
            'meta' => null,
        ]);
    }

    public function test_validation_failure_returns_the_envelope_as_json(): void
    {
        Route::middleware('api')->post('/api/v1/_conventions-probe', function () {
            request()->validate(['email' => 'required|email:rfc,strict']);
        });

        $response = $this->post('/api/v1/_conventions-probe', ['email' => 'nonsense']);

        $response->assertStatus(422);
        $response->assertHeader('content-type', 'application/json');
        $response->assertJson([
            'success' => false,
            'message' => 'The given data was invalid.',
            'data' => null,
            'meta' => null,
        ]);
        $response->assertJsonStructure(['errors' => ['email']]);
    }

    public function test_wrong_method_returns_405_with_an_allow_header(): void
    {
        Route::middleware('api')->post('/api/v1/_conventions-method', fn () => 'ok');

        $response = $this->get('/api/v1/_conventions-method');

        $response->assertStatus(405);
        $response->assertHeader('content-type', 'application/json');
        $response->assertHeader('allow');
        $response->assertJsonPath('success', false);
    }

    public function test_abort_preserves_its_status_and_message(): void
    {
        Route::middleware('api')->get('/api/v1/_conventions-abort', function () {
            abort(409, 'Todo already completed.');
        });

        $response = $this->get('/api/v1/_conventions-abort');

        $response->assertStatus(409);
        $response->assertHeader('content-type', 'application/json');
        $response->assertExactJson([
            'success' => false,
            'message' => 'Todo already completed.',
            'data' => null,
            'errors' => null,
            'meta' => null,
        ]);
    }

    /**
     * An abort with no message must still produce something readable rather
     * than an empty string.
     */
    public function test_abort_without_a_message_falls_back_to_the_status_text(): void
    {
        Route::middleware('api')->get('/api/v1/_conventions-abort-bare', function () {
            abort(402);
        });

        $response = $this->get('/api/v1/_conventions-abort-bare');

        $response->assertStatus(402);
        $response->assertJsonPath('success', false);
        $response->assertJsonPath('message', 'Payment Required');
    }
}
