<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\TodoPriority;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\BulkCompleteTodosRequest;
use App\Http\Requests\Api\V1\IndexTodoRequest;
use App\Http\Requests\Api\V1\StoreTodoRequest;
use App\Http\Requests\Api\V1\UpdateTodoRequest;
use App\Http\Resources\TodoResource;
use App\Http\Responses\ApiResponse;
use App\Models\User;
use App\Services\TodoService;
use Illuminate\Http\JsonResponse;

class TodoController extends Controller
{
    public function __construct(
        private readonly TodoService $todos,
    ) {}

    public function index(IndexTodoRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = auth()->user();

        $priority = $request->filled('priority')
            ? TodoPriority::from($request->string('priority')->toString())
            : null;

        $paginator = $this->todos->list(
            $user->id,
            $request->has('is_completed') ? $request->boolean('is_completed') : null,
            $priority,
            $request->string('sort', 'created_at')->toString(),
            $request->string('order', 'desc')->toString(),
            $request->has('per_page') ? $request->integer('per_page') : null,
        );

        return ApiResponse::paginated(
            $paginator,
            'OK.',
            static fn (mixed $todo): TodoResource => new TodoResource($todo),
        );
    }

    public function store(StoreTodoRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = auth()->user();

        $todo = $this->todos->create($user->id, $request->validated());

        return ApiResponse::created(new TodoResource($todo), 'Todo created.');
    }

    public function show(int $id): JsonResponse
    {
        /** @var User $user */
        $user = auth()->user();

        return ApiResponse::success(
            new TodoResource($this->todos->find($user->id, $id)),
            'OK.',
        );
    }

    public function update(UpdateTodoRequest $request, int $id): JsonResponse
    {
        /** @var User $user */
        $user = auth()->user();

        $todo = $this->todos->update($user->id, $id, $request->validated());

        return ApiResponse::success(new TodoResource($todo), 'Todo updated.');
    }

    public function destroy(int $id): JsonResponse
    {
        /** @var User $user */
        $user = auth()->user();

        $this->todos->delete($user->id, $id);

        return ApiResponse::success(null, 'Todo deleted.');
    }

    public function bulkComplete(BulkCompleteTodosRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = auth()->user();

        /** @var list<int> $ids */
        $ids = array_map('intval', $request->validated('ids'));

        $status = $this->todos->bulkComplete($user->id, $ids);

        return ApiResponse::accepted([
            'job_id' => $status->uuid,
            'status' => $status->status->value,
            'status_url' => route('api.v1.jobs.show', ['uuid' => $status->uuid]),
        ]);
    }
}
