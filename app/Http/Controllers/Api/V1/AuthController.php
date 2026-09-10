<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\DataTransferObjects\AuthResult;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\LoginRequest;
use App\Http\Requests\Api\V1\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Http\Responses\ApiResponse;
use App\Models\User;
use App\Services\AuthService;
use Illuminate\Http\JsonResponse;

class AuthController extends Controller
{
    public function __construct(
        private readonly AuthService $auth,
    ) {}

    public function register(RegisterRequest $request): JsonResponse
    {
        $result = $this->auth->register(
            $request->string('name')->toString(),
            $request->string('email')->toString(),
            $request->string('password')->toString(),
        );

        return ApiResponse::created($this->payload($result), 'Account created.');
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $result = $this->auth->login(
            $request->string('email')->toString(),
            $request->string('password')->toString(),
        );

        return ApiResponse::success($this->payload($result), 'Signed in.');
    }

    public function logout(): JsonResponse
    {
        $this->auth->logout();

        return ApiResponse::success(null, 'Signed out.');
    }

    public function refresh(): JsonResponse
    {
        /** @var User $user */
        $user = auth()->user();

        return ApiResponse::success(
            $this->payload($this->auth->refresh($user)),
            'Token refreshed.',
        );
    }

    private function payload(AuthResult $result): array
    {
        return [
            'user' => new UserResource($result->user),
            'token' => $result->token,
            'token_type' => 'bearer',
            'expires_in' => $result->expiresIn,
        ];
    }
}
