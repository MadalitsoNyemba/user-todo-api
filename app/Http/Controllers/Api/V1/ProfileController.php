<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\UpdateProfileRequest;
use App\Http\Resources\UserResource;
use App\Http\Responses\ApiResponse;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Http\JsonResponse;

class ProfileController extends Controller
{
    public function __construct(
        private readonly UserService $users,
    ) {}

    public function show(): JsonResponse
    {
        /** @var User $user */
        $user = auth()->user();

        $this->authorize('view', $user);

        return ApiResponse::success(
            new UserResource($this->users->profile($user->id)),
            'OK.',
        );
    }

    public function update(UpdateProfileRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = auth()->user();

        $this->authorize('update', $user);

        $updated = $this->users->update(
            $user,
            $request->string('name')->toString(),
            $request->string('email')->toString(),
            $request->filled('password') ? $request->string('password')->toString() : null,
        );

        return ApiResponse::success(new UserResource($updated), 'Profile updated.');
    }

    public function destroy(): JsonResponse
    {
        /** @var User $user */
        $user = auth()->user();

        $this->authorize('delete', $user);

        $this->users->deleteAccount($user);

        return ApiResponse::success(null, 'Account deleted.');
    }
}
