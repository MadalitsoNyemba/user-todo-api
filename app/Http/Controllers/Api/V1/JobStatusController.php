<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\JobStatusResource;
use App\Http\Responses\ApiResponse;
use App\Models\User;
use App\Services\JobStatusService;
use Illuminate\Http\JsonResponse;

class JobStatusController extends Controller
{
    public function __construct(
        private readonly JobStatusService $jobs,
    ) {}

    public function show(string $uuid): JsonResponse
    {
        /** @var User $user */
        $user = auth()->user();

        return ApiResponse::success(
            new JobStatusResource($this->jobs->findForUser($user->id, $uuid)),
            'OK.',
        );
    }
}
