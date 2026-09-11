<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\IndexUsersRequest;
use App\Http\Resources\UserResource;
use App\Http\Responses\ApiResponse;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Http\JsonResponse;

class UserController extends Controller
{
    public function __construct(
        private readonly UserService $users,
    ) {}

    public function index(IndexUsersRequest $request): JsonResponse
    {
        $this->authorize('viewAny', User::class);

        $paginator = $this->users->list(
            $request->has('per_page') ? $request->integer('per_page') : null,
        );

        return ApiResponse::paginated(
            $paginator,
            'OK.',
            static fn (mixed $user): UserResource => new UserResource($user),
        );
    }
}
